<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\ProductVariant;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckoutRecoveryController extends Controller
{
    public function __invoke(Request $request, CheckoutSnapshot $snapshot): RedirectResponse
    {
        $user = $request->user();

        if ($snapshot->user_id !== null) {
            if ($user === null) {
                return redirect()->guest(route('login'));
            }

            abort_if($user->id !== $snapshot->user_id, 403);
        }

        $skipped = [];

        DB::transaction(function () use ($snapshot, $user, &$skipped) {
            $cart = $user !== null
                ? Cart::firstOrCreate(['user_id' => $user->id])
                : Cart::firstOrCreate(['session_id' => session()->getId()]);

            $maxPerLine = (int) settings('orders.max_qty_per_line', 0);

            foreach ((array) $snapshot->lines as $line) {
                $variant = ProductVariant::with('product')->find($line['product_variant_id'] ?? null);
                $product = $variant?->product;

                if (! $variant || ! $product || $product->status !== 'active' || ! $variant->canAddToCart(1)) {
                    $skipped[] = (string) ($line['product_name'] ?? '');

                    continue;
                }

                $item = $cart->cartItems()->where('product_variant_id', $variant->id)->first();
                $quantity = max((int) ($line['quantity'] ?? 1), (int) ($item?->quantity ?? 0));
                $quantity = min($quantity, (int) $variant->stock_quantity);

                if ($maxPerLine > 0) {
                    $quantity = min($quantity, $maxPerLine);
                }

                if ($item) {
                    $item->update(['quantity' => $quantity, 'price' => $variant->price]);
                } else {
                    $cart->cartItems()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'size_id' => $product->size_id,
                        'quantity' => $quantity,
                        'price' => $variant->price,
                    ]);
                }
            }

            if ($snapshot->recovered_at === null) {
                $snapshot->forceFill(['recovered_at' => now()])->save();
            }
        });

        $this->reapplyCoupon($snapshot);

        $redirect = redirect()->route('cart');

        return $skipped === []
            ? $redirect->with('success', __('newsletter.recovery.restored'))
            : $redirect->with('warning', __('newsletter.recovery.partly_restored', ['items' => implode(', ', array_filter($skipped))]));
    }

    private function reapplyCoupon(CheckoutSnapshot $snapshot): void
    {
        if (! $snapshot->coupon_code || session()->has('applied_coupon')) {
            return;
        }

        try {
            $promotions = app(PromotionService::class);
            $validation = $promotions->validatePromotionCode($snapshot->coupon_code);

            if (! ($validation['valid'] ?? false)) {
                return;
            }

            $discount = $promotions->calculateDiscount($validation['coupon'], (float) $snapshot->subtotal);

            session(['applied_coupon' => [
                'code' => $snapshot->coupon_code,
                'promotion_code_id' => $validation['promotion_code']->id,
                'coupon_id' => $validation['coupon']->id,
                'name' => $validation['coupon']->name,
                'discount_type' => $discount['type'],
                'discount_amount' => $discount['amount'],
                'discount_percentage' => $discount['percentage'],
                'description' => $discount['description'],
            ]]);
        } catch (Throwable $e) {
            Log::info('Coupon of a recovered checkout not re-applied', ['snapshot_id' => $snapshot->id, 'error' => $e->getMessage()]);
        }
    }
}
