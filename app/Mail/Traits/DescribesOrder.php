<?php

namespace App\Mail\Traits;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Carbon\CarbonInterface;

trait DescribesOrder
{
    protected function loadOrderRelations(Order $order): Order
    {
        return $order->loadMissing([
            'user',
            'billingAddress',
            'shippingAddress',
            'orderItems.product.defaultVariant',
            'orderItems.productVariant',
            'orderItems.size',
        ]);
    }

    protected function customerName(Order $order): ?string
    {
        $name = trim((string) $order->user?->name);

        if ($name === '') {
            $name = trim((string) $order->billingAddress?->first_name);
        }

        if ($name === '') {
            $name = trim((string) $order->shippingAddress?->first_name);
        }

        return $name !== '' ? $name : null;
    }

    protected function greeting(?string $name): string
    {
        return $name !== null && $name !== ''
            ? __('emails.common.greeting', ['name' => $name])
            : __('emails.common.greeting_generic');
    }

    protected function orderUrl(Order $order): string
    {
        return $order->user_id !== null ? route('orders.index') : $order->publicUrl();
    }

    /**
     * Data every customer order email shares.
     *
     * @return array<string, mixed>
     */
    protected function orderContext(Order $order): array
    {
        $this->loadOrderRelations($order);

        $name = $this->customerName($order);

        return [
            'order' => $order,
            'orderNumber' => '#'.$order->order_number,
            'orderDate' => $this->formatDate($order->created_at),
            'customerName' => $name,
            'greeting' => $this->greeting($name),
            'items' => $this->orderLines($order),
            'totals' => $this->orderTotals($order),
            'shippingAddress' => $this->addressLines($order->shippingAddress),
            'orderUrl' => $this->orderUrl($order),
            'isGuest' => $order->user_id === null,
            'publicUrlDays' => Order::publicUrlDays(),
            'supportAddress' => config('mail.support_address'),
        ];
    }

    /**
     * @return array<int, array{name: string, options: array<int, string>, quantity: int, unit: string, total: string, image: string}>
     */
    protected function orderLines(Order $order): array
    {
        return $order->orderItems->map(function (OrderItem $item) use ($order) {
            $product = $item->product_id ? $item->product : null;
            $product = $product?->exists ? $product : null;
            $variant = $item->productVariant;

            $options = [];

            if ($variant && ($variant->name !== 'Standard' || ! $variant->is_default)) {
                $options[] = __('emails.common.variant', ['variant' => $variant->name]);
            }

            if ($item->size?->name) {
                $options[] = __('emails.common.size', ['size' => $item->size->name]);
            }

            $sku = $this->lineSku($product, $variant);

            if ($sku) {
                $options[] = __('emails.common.sku', ['sku' => $sku]);
            }

            return [
                'name' => (string) ($item->product_name ?: $product?->name),
                'options' => $options,
                'quantity' => (int) $item->quantity,
                'unit' => $this->money($item->price, $order->currency),
                'total' => $this->money($item->total, $order->currency),
                'image' => $this->lineImage($product, $variant),
            ];
        })->all();
    }

    /**
     * Items → discount (with coupon) → shipping → total, always summing to the stored total. Shipping is always
     * an amount (a zero amount is printed as such, never as a word).
     *
     * Orders store subtotal, discount and shipping separately; if a legacy row does not add up,
     * the discount is derived from the charged total so the customer sees consistent lines.
     * Prices are VAT-inclusive: orders that stored their VAT rate (orders.tax_rate) get a last, muted row
     * "of which VAT (22%) €x" (the amount contained in the total, orders.tax_amount); older orders have no rate
     * and keep the plain "VAT included" sentence in `vat_note`.
     *
     * @return array{rows: array<int, array{label: string, value: string, bold: bool, muted?: bool}>, vat_note: string|null}
     */
    protected function orderTotals(Order $order, bool $withBreakdown = true): array
    {
        $currency = $order->currency;
        $total = round((float) $order->total_amount, 2);
        $shipping = round((float) $order->shipping_amount, 2);
        $subtotal = round((float) $order->subtotal, 2);
        $discount = round((float) $order->discount_amount, 2);

        if (abs($subtotal - $discount + $shipping - $total) > 0.005) {
            $discount = max(0.0, round($subtotal + $shipping - $total, 2));

            if (abs($subtotal - $discount + $shipping - $total) > 0.005) {
                $subtotal = round($total + $discount - $shipping, 2);
            }
        }

        $rows = [];

        if ($withBreakdown) {
            $rows[] = ['label' => __('emails.common.subtotal'), 'value' => $this->money($subtotal, $currency), 'bold' => false];

            if ($discount > 0) {
                $rows[] = [
                    'label' => $order->coupon_code
                        ? __('emails.common.discount_coupon', ['code' => $order->coupon_code])
                        : __('emails.common.discount'),
                    'value' => '-'.$this->money($discount, $currency),
                    'bold' => false,
                ];
            }

            $rows[] = [
                'label' => __('emails.common.shipping'),
                'value' => $this->money($shipping, $currency),
                'bold' => false,
            ];
        }

        $rows[] = ['label' => __('emails.common.total'), 'value' => $this->money($total, $currency), 'bold' => true];

        if ($order->tax_rate === null) {
            return ['rows' => $rows, 'vat_note' => __('emails.common.vat_included')];
        }

        $rows[] = [
            'label' => __('emails.common.vat_portion', ['rate' => $this->formatRate((float) $order->tax_rate)]),
            'value' => $this->money((float) $order->tax_amount, $currency),
            'bold' => false,
            'muted' => true,
        ];

        return ['rows' => $rows, 'vat_note' => null];
    }

    protected function formatRate(float $rate): string
    {
        $separator = app()->getLocale() === 'en' ? '.' : ',';

        return rtrim(rtrim(number_format($rate, 2, $separator, ''), '0'), $separator);
    }

    /**
     * Non-empty address lines, ready to print one per line.
     *
     * @return array<int, string>
     */
    protected function addressLines(?Address $address): array
    {
        if (! $address) {
            return [];
        }

        return array_values(array_filter(array_map('trim', [
            trim($address->first_name.' '.$address->last_name),
            (string) $address->company,
            (string) $address->address_line_1,
            (string) $address->address_line_2,
            trim($address->postal_code.' '.$address->city),
            implode(', ', array_filter([trim((string) $address->state), trim((string) $address->country)])),
        ]), fn ($line) => $line !== ''));
    }

    /**
     * The single delivery estimate (config/shipping.php), as "1-2" / "3-5" strings.
     *
     * @return array{dispatch: string, delivery: string}
     */
    protected function deliveryEstimate(): array
    {
        $config = config('shipping.delivery_estimate');

        return [
            'dispatch' => $this->range((int) $config['dispatch_min'], (int) $config['dispatch_max']),
            'delivery' => $this->range((int) $config['delivery_min'], (int) $config['delivery_max']),
        ];
    }

    protected function range(int $min, int $max): string
    {
        return $min >= $max ? (string) $max : $min.'-'.$max;
    }

    protected function formatDate(?CarbonInterface $date): string
    {
        return $date ? $date->copy()->locale(app()->getLocale())->isoFormat('LL') : '';
    }

    protected function money(float|string|null $amount, ?string $currency): string
    {
        return Money::format($amount, $currency);
    }

    private function lineSku($product, $variant): ?string
    {
        if (! $product) {
            return null;
        }

        if ($variant && $product->base_sku) {
            return $product->base_sku.$variant->sku_suffix;
        }

        return $product->sku ?: ($product->base_sku ?: null);
    }

    private function lineImage($product, $variant): string
    {
        $placeholder = asset('images/product.png');

        if (! $product) {
            return $placeholder;
        }

        $candidates = [];

        $variantImage = is_array($variant?->images) ? ($variant->images[0] ?? null) : null;

        if ($variantImage) {
            $candidates[] = $variantImage;
        }

        $productImage = is_array($product->images) ? ($product->images[0] ?? null) : null;

        if ($productImage) {
            $candidates[] = $productImage;
        }

        foreach ($candidates as $index => $image) {
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                return $image;
            }

            $relative = ($index === 0 && $variantImage)
                ? "storage/products/{$product->id}/variants/{$variant->id}/".basename($image)
                : "storage/products/{$product->id}/".basename($image);

            if (is_file(public_path($relative))) {
                return asset($relative);
            }
        }

        return $placeholder;
    }
}
