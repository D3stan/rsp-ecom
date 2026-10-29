<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Coupon;
use Stripe\Exception\ApiErrorException;
use Stripe\PromotionCode;

class PromotionService
{
    /**
     * Validate a promotion code against everything that can be set on it in the Stripe Dashboard.
     *
     * The shop charges the discounted amount itself and never passes the code to Stripe, so Stripe's times_redeemed
     * only counts uses outside the shop: usage is that plus the paid orders that carry the code. Pass what the caller
     * knows: the items total enables the minimum-amount check, the user or email the first-order-only check. A check
     * whose input is missing is skipped here and runs again right before charging, when both are known.
     *
     * @return array{valid: bool, error?: string, promotion_code?: PromotionCode, coupon?: Coupon, discount?: array<string, mixed>}
     */
    public function validatePromotionCode(string $code, ?float $itemsTotal = null, ?User $user = null, ?string $email = null): array
    {
        try {
            $promotionCodes = PromotionCode::all([
                'code' => $code,
                'active' => true,
                'limit' => 1,
                'expand' => ['data.coupon.applies_to'],
            ]);

            if (empty($promotionCodes->data)) {
                return ['valid' => false, 'error' => __('coupon.not_found')];
            }

            $promotionCode = $promotionCodes->data[0];
            $coupon = $promotionCode->coupon;

            if ($error = $this->restrictionError($promotionCode, $coupon, $itemsTotal, $user, $email)) {
                return ['valid' => false, 'error' => $error];
            }

            return [
                'valid' => true,
                'promotion_code' => $promotionCode,
                'coupon' => $coupon,
                'discount' => $this->calculateDiscount($coupon),
            ];

        } catch (ApiErrorException $e) {
            Log::error('Error validating promotion code', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return ['valid' => false, 'error' => __('coupon.check_failed')];
        }
    }

    /**
     * Why the shop cannot honour this code right now, or null when it can.
     */
    private function restrictionError(PromotionCode $promotionCode, Coupon $coupon, ?float $itemsTotal, ?User $user, ?string $email): ?string
    {
        if (! $coupon->valid) {
            return __('coupon.not_valid');
        }

        $now = time();
        $couponExpired = ($coupon->redeem_by ?? null) && $coupon->redeem_by < $now;
        $codeExpired = ($promotionCode->expires_at ?? null) && $promotionCode->expires_at < $now;
        if ($couponExpired || $codeExpired) {
            return __('coupon.expired');
        }

        // Codes limited to Stripe customers or Stripe products cannot be matched to the shop's accounts and cart lines
        if (($promotionCode->customer ?? null) || ! empty($coupon->applies_to->products ?? [])) {
            return __('coupon.not_available');
        }

        // shortcut: two checkouts paying at the same moment can both take the last use; reserve a use at checkout start if that ever matters
        if (($promotionCode->max_redemptions ?? null)
            && ($promotionCode->times_redeemed ?? 0) + $this->paidOrdersWith([$promotionCode->code]) >= $promotionCode->max_redemptions) {
            return __('coupon.used_up');
        }

        if (($coupon->max_redemptions ?? null)
            && ($coupon->times_redeemed ?? 0) + $this->paidOrdersWith($this->codesOf($coupon, $promotionCode)) >= $coupon->max_redemptions) {
            return __('coupon.used_up');
        }

        $restrictions = $promotionCode->restrictions ?? null;
        $minimumCents = $restrictions->minimum_amount ?? null;

        if ($minimumCents && $itemsTotal !== null && (int) round($itemsTotal * 100) < $minimumCents) {
            $currency = strtoupper((string) ($restrictions->minimum_amount_currency ?? settings('store.currency', 'EUR')));

            return __('coupon.minimum_amount', ['amount' => Money::format($minimumCents / 100, $currency)]);
        }

        if (($restrictions->first_time_transaction ?? false) && ($user || $email) && $this->hasPaidOrder($user, $email)) {
            return __('coupon.first_order_only');
        }

        return null;
    }

    /**
     * Paid orders (refunded ones included: the use happened) that carry one of these codes, ignoring case like Stripe.
     *
     * @param  array<int, string>  $codes
     */
    private function paidOrdersWith(array $codes): int
    {
        return Order::query()
            ->whereIn('payment_status', Order::CAPTURED_PAYMENT_STATUSES)
            ->whereIn(DB::raw('LOWER(coupon_code)'), array_map('strtolower', $codes))
            ->count();
    }

    /**
     * Every promotion code of a coupon, so a coupon-wide limit counts the uses of all of them.
     *
     * @return array<int, string>
     */
    private function codesOf(Coupon $coupon, PromotionCode $known): array
    {
        $codes = collect(PromotionCode::all(['coupon' => $coupon->id, 'limit' => 100])->data)->pluck('code');

        return $codes->push($known->code)->unique()->values()->all();
    }

    /**
     * Whether this customer already paid an order, as an account or as a guest with the same email.
     */
    private function hasPaidOrder(?User $user, ?string $email): bool
    {
        $emails = array_values(array_filter([$email, $user?->email]));

        return Order::query()
            ->whereIn('payment_status', Order::CAPTURED_PAYMENT_STATUSES)
            ->where(function ($query) use ($user, $emails) {
                if ($user) {
                    $query->where('user_id', $user->id);
                }
                if ($emails) {
                    $query->orWhereIn('guest_email', $emails);
                }
            })
            ->exists();
    }

    /**
     * Calculate discount amount from coupon
     */
    public function calculateDiscount(Coupon $coupon, float $subtotal = 0): array
    {
        $discount = [
            'type' => null,
            'amount' => 0,
            'percentage' => 0,
            'description' => '',
        ];

        if ($coupon->amount_off) {
            // Fixed amount discount
            $discount['type'] = 'fixed';
            $discount['amount'] = $coupon->amount_off / 100; // Convert from cents
            $discount['description'] = '$'.number_format($discount['amount'], 2).' off';
        } elseif ($coupon->percent_off) {
            // Percentage discount
            $discount['type'] = 'percentage';
            $discount['percentage'] = $coupon->percent_off;
            $discount['amount'] = $subtotal * ($coupon->percent_off / 100);
            $discount['description'] = $coupon->percent_off.'% off';
        }

        return $discount;
    }

    /**
     * Apply promotion code to checkout session
     */
    public function applyPromotionCodeToSession(array &$sessionParams, string $promotionCode): bool
    {
        $validation = $this->validatePromotionCode($promotionCode);

        if (! $validation['valid']) {
            Log::warning('Invalid promotion code applied to session', [
                'code' => $promotionCode,
                'error' => $validation['error'],
            ]);

            return false;
        }

        // Add promotion code to session
        $sessionParams['discounts'] = [
            ['promotion_code' => $validation['promotion_code']->id],
        ];

        Log::info('Promotion code applied to checkout session', [
            'code' => $promotionCode,
            'promotion_code_id' => $validation['promotion_code']->id,
        ]);

        return true;
    }

    /**
     * Create a new coupon in Stripe
     */
    public function createCoupon(array $couponData): ?Coupon
    {
        try {
            $params = [
                'id' => $couponData['id'] ?? null,
                'name' => $couponData['name'],
                'duration' => $couponData['duration'], // 'forever', 'once', 'repeating'
            ];

            // Add discount amount
            if (isset($couponData['amount_off'])) {
                $params['amount_off'] = $couponData['amount_off'] * 100; // Convert to cents
                $params['currency'] = $couponData['currency'] ?? 'usd';
            } elseif (isset($couponData['percent_off'])) {
                $params['percent_off'] = $couponData['percent_off'];
            }

            // Add optional parameters
            if (isset($couponData['duration_in_months'])) {
                $params['duration_in_months'] = $couponData['duration_in_months'];
            }

            if (isset($couponData['max_redemptions'])) {
                $params['max_redemptions'] = $couponData['max_redemptions'];
            }

            if (isset($couponData['redeem_by'])) {
                $params['redeem_by'] = $couponData['redeem_by'];
            }

            if (isset($couponData['metadata'])) {
                $params['metadata'] = $couponData['metadata'];
            }

            $coupon = Coupon::create($params);

            Log::info('Coupon created successfully', [
                'coupon_id' => $coupon->id,
                'name' => $coupon->name,
            ]);

            return $coupon;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create coupon', [
                'error' => $e->getMessage(),
                'coupon_data' => $couponData,
            ]);

            return null;
        }
    }

    /**
     * Create a promotion code for a coupon
     */
    public function createPromotionCode(string $couponId, array $promotionData): ?PromotionCode
    {
        try {
            $params = [
                'coupon' => $couponId,
                'code' => $promotionData['code'],
            ];

            // Add optional parameters
            if (isset($promotionData['active'])) {
                $params['active'] = $promotionData['active'];
            }

            if (isset($promotionData['customer'])) {
                $params['customer'] = $promotionData['customer'];
            }

            if (isset($promotionData['expires_at'])) {
                $params['expires_at'] = $promotionData['expires_at'];
            }

            if (isset($promotionData['max_redemptions'])) {
                $params['max_redemptions'] = $promotionData['max_redemptions'];
            }

            if (isset($promotionData['metadata'])) {
                $params['metadata'] = $promotionData['metadata'];
            }

            $promotionCode = PromotionCode::create($params);

            Log::info('Promotion code created successfully', [
                'promotion_code_id' => $promotionCode->id,
                'code' => $promotionCode->code,
                'coupon_id' => $couponId,
            ]);

            return $promotionCode;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create promotion code', [
                'error' => $e->getMessage(),
                'coupon_id' => $couponId,
                'promotion_data' => $promotionData,
            ]);

            return null;
        }
    }

    /**
     * List active promotion codes
     */
    public function getActivePromotionCodes(int $limit = 20): array
    {
        try {
            $promotionCodes = PromotionCode::all([
                'active' => true,
                'limit' => $limit,
            ]);

            $codes = [];
            foreach ($promotionCodes->data as $promotionCode) {
                $codes[] = [
                    'id' => $promotionCode->id,
                    'code' => $promotionCode->code,
                    'coupon' => [
                        'id' => $promotionCode->coupon->id,
                        'name' => $promotionCode->coupon->name,
                        'amount_off' => $promotionCode->coupon->amount_off,
                        'percent_off' => $promotionCode->coupon->percent_off,
                        'duration' => $promotionCode->coupon->duration,
                        'times_redeemed' => $promotionCode->coupon->times_redeemed,
                        'max_redemptions' => $promotionCode->coupon->max_redemptions,
                    ],
                    'active' => $promotionCode->active,
                    'expires_at' => $promotionCode->expires_at,
                    'max_redemptions' => $promotionCode->max_redemptions,
                    'times_redeemed' => $promotionCode->times_redeemed,
                ];
            }

            return $codes;

        } catch (ApiErrorException $e) {
            Log::error('Failed to fetch promotion codes', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Deactivate a promotion code
     */
    public function deactivatePromotionCode(string $promotionCodeId): bool
    {
        try {
            PromotionCode::update($promotionCodeId, ['active' => false]);

            Log::info('Promotion code deactivated', [
                'promotion_code_id' => $promotionCodeId,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to deactivate promotion code', [
                'error' => $e->getMessage(),
                'promotion_code_id' => $promotionCodeId,
            ]);

            return false;
        }
    }
}
