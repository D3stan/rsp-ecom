<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Stripe\Coupon;
use Stripe\PromotionCode;
use Stripe\Exception\ApiErrorException;

class PromotionService
{
    /**
     * Validate a promotion code
     */
    public function validatePromotionCode(string $code): array
    {
        try {
            $promotionCodes = PromotionCode::all([
                'code' => $code,
                'active' => true,
                'limit' => 1,
            ]);

            if (empty($promotionCodes->data)) {
                return [
                    'valid' => false,
                    'error' => 'Promotion code not found or inactive.',
                ];
            }

            $promotionCode = $promotionCodes->data[0];
            $coupon = $promotionCode->coupon;

            // Check if coupon is valid
            if (!$coupon->valid) {
                return [
                    'valid' => false,
                    'error' => 'Coupon is no longer valid.',
                ];
            }

            // Check expiration
            if ($coupon->redeem_by && $coupon->redeem_by < time()) {
                return [
                    'valid' => false,
                    'error' => 'Promotion code has expired.',
                ];
            }

            // Check usage limits
            if ($coupon->max_redemptions && $coupon->times_redeemed >= $coupon->max_redemptions) {
                return [
                    'valid' => false,
                    'error' => 'Promotion code usage limit exceeded.',
                ];
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

            return [
                'valid' => false,
                'error' => 'Error validating promotion code.',
            ];
        }
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
            $discount['description'] = '$' . number_format($discount['amount'], 2) . ' off';
        } elseif ($coupon->percent_off) {
            // Percentage discount
            $discount['type'] = 'percentage';
            $discount['percentage'] = $coupon->percent_off;
            $discount['amount'] = $subtotal * ($coupon->percent_off / 100);
            $discount['description'] = $coupon->percent_off . '% off';
        }

        return $discount;
    }

    /**
     * Apply promotion code to checkout session
     */
    public function applyPromotionCodeToSession(array &$sessionParams, string $promotionCode): bool
    {
        $validation = $this->validatePromotionCode($promotionCode);

        if (!$validation['valid']) {
            Log::warning('Invalid promotion code applied to session', [
                'code' => $promotionCode,
                'error' => $validation['error'],
            ]);
            return false;
        }

        // Add promotion code to session
        $sessionParams['discounts'] = [
            ['promotion_code' => $validation['promotion_code']->id]
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
