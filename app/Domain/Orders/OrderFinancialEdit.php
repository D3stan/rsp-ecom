<?php

namespace App\Domain\Orders;

use App\Exceptions\OrderEditRefused;
use App\Models\Order;
use App\Support\Vat;
use Illuminate\Support\Facades\Validator;

/**
 * The money side of an admin order edit (Admin\OrdersController::update).
 *
 * - payment_status is editable by admins for orders paid outside Stripe; see assertPaymentStatusEditable().
 * - Once the payment is captured or in flight (Order::financialsLocked) lines, quantities, prices, shipping, discount
 *   and totals are locked: sending them unchanged is fine, any difference is refused. Money goes back through a refund.
 * - On an unpaid order the lines (quantity int >= 1, price >= 0; a line left out is deleted) and the shipping cost may
 *   change; subtotal, VAT portion and total are then derived on the server, discount included. Totals the client
 *   sends are ignored there.
 *
 * plan() only checks and returns what apply() will write; nothing is saved by either (the caller saves, in one
 * transaction).
 */
final class OrderFinancialEdit
{
    private const LOCKED_MONEY_FIELDS = ['subtotal', 'shipping_amount', 'tax_amount', 'discount_amount', 'total_amount'];

    /**
     * @param  array<string, mixed>  $input  the request fields
     * @return array{lines: array<int, array{quantity: int, price: int}>|null, shipping: int|null}|null what apply() writes;
     *                                                                                                  null: nothing financial
     *
     * @throws OrderEditRefused
     */
    public static function plan(Order $order, array $input): ?array
    {
        if ($order->financialsLocked()) {
            self::assertUnchanged($order, $input);

            return null;
        }

        $lines = array_key_exists('order_items', $input) ? self::parseLines($order, $input['order_items']) : null;
        $shipping = array_key_exists('shipping_amount', $input) ? self::cents($input['shipping_amount']) : null;

        return $lines === null && $shipping === null ? null : ['lines' => $lines, 'shipping' => $shipping];
    }

    /**
     * Admins may correct the payment status of an order paid outside Stripe (bank transfer, cash). For an order paid
     * through Stripe the captured states follow Stripe and the refunds flow: an edit cannot take them back, and
     * "refunded" or "partially_refunded" are only ever written by RefundService.
     *
     * @throws OrderEditRefused
     */
    public static function assertPaymentStatusEditable(Order $order, string $to): void
    {
        if (! $order->hasStripePaymentIntent() && ! $order->hasStripeCheckoutSession()) {
            return;
        }

        $claimsRefund = in_array($to, ['refunded', 'partially_refunded'], true);

        if ($order->hasCapturedPayment() || $claimsRefund) {
            throw OrderEditRefused::paymentStatus();
        }
    }

    /**
     * Write the planned lines (update, delete the ones left out) and fill the derived totals on the order (not saved).
     *
     * @param  array{lines: array<int, array{quantity: int, price: int}>|null, shipping: int|null}  $plan
     */
    public static function apply(Order $order, array $plan): void
    {
        $subtotal = 0;

        foreach ($order->orderItems()->get() as $item) {
            $line = $plan['lines'] === null ? null : ($plan['lines'][$item->id] ?? false);

            if ($line === false) {
                $item->delete();

                continue;
            }

            $quantity = $line['quantity'] ?? (int) $item->quantity;
            $price = $line['price'] ?? self::cents($item->price);
            $total = $quantity * $price;

            if ($line !== null) {
                $item->update(['quantity' => $quantity, 'price' => $price / 100, 'total' => $total / 100]);
            }

            $subtotal += $total;
        }

        $shipping = $plan['shipping'] ?? self::cents($order->shipping_amount);
        $discount = self::cents($order->discount_amount);
        // Orders without a VAT snapshot (legacy) carry their tax on top of the total; the others contain it
        $taxOnTop = $order->tax_rate === null ? self::cents($order->tax_amount) : 0;
        $total = max(0, $subtotal + $shipping - $discount + $taxOnTop);

        $order->fill([
            'subtotal' => $subtotal / 100,
            'shipping_amount' => $shipping / 100,
            'total_amount' => $total / 100,
            'tax_amount' => $order->tax_rate === null ? $order->tax_amount : Vat::portion($total / 100, (float) $order->tax_rate),
        ]);
    }

    /**
     * @throws OrderEditRefused when a locked (paid) order would change
     */
    private static function assertUnchanged(Order $order, array $input): void
    {
        foreach (self::LOCKED_MONEY_FIELDS as $field) {
            if (array_key_exists($field, $input) && self::cents($input[$field]) !== self::cents($order->{$field})) {
                throw OrderEditRefused::financialsLocked((string) $order->order_number);
            }
        }

        if (! array_key_exists('order_items', $input)) {
            return;
        }

        $sent = collect(self::decodeLines($input['order_items']))->keyBy(fn ($line) => (int) ($line['id'] ?? 0));
        $stored = $order->orderItems()->get()->keyBy('id');

        $changed = $sent->keys()->sort()->values()->all() !== $stored->keys()->sort()->values()->all()
            || $stored->contains(fn ($item) => (int) ($sent[$item->id]['quantity'] ?? null) !== (int) $item->quantity
                || ! is_numeric($sent[$item->id]['quantity'] ?? null)
                || self::cents($sent[$item->id]['price'] ?? $item->price) !== self::cents($item->price));

        if ($changed) {
            throw OrderEditRefused::financialsLocked((string) $order->order_number);
        }
    }

    /**
     * @return array<int, array{quantity: int, price: int}> order item id => quantity and unit price in cents
     *
     * @throws OrderEditRefused
     */
    private static function parseLines(Order $order, mixed $raw): array
    {
        $lines = self::decodeLines($raw);

        $validator = Validator::make(['lines' => $lines], [
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'required|integer|distinct',
            'lines.*.quantity' => 'required|integer|min:1|max:10000',
            'lines.*.price' => 'required|numeric|min:0|max:1000000',
        ], [
            'lines.required' => 'an order needs at least one line.',
            'lines.min' => 'an order needs at least one line.',
        ]);

        if ($validator->fails()) {
            throw OrderEditRefused::invalidLines($validator->errors()->first());
        }

        $known = $order->orderItems()->pluck('id')->all();
        $parsed = [];

        foreach ($lines as $line) {
            if (! in_array((int) $line['id'], $known, true)) {
                throw OrderEditRefused::invalidLines('a line does not belong to this order.');
            }

            $parsed[(int) $line['id']] = ['quantity' => (int) $line['quantity'], 'price' => self::cents($line['price'])];
        }

        return $parsed;
    }

    /**
     * The edit form sends the lines as a JSON string; an array is accepted too.
     *
     * @return array<int, mixed>
     */
    private static function decodeLines(mixed $raw): array
    {
        $lines = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($lines) || ! array_is_list($lines)) {
            throw OrderEditRefused::invalidLines('the lines could not be read.');
        }

        return $lines;
    }

    private static function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
