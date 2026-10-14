<?php

namespace Tests\Feature\Mail\Content\Support;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

class EmailFixtures
{
    public const LOCALES = ['it', 'en', 'de', 'es', 'fr'];

    public const LONG_NAME = 'Fari LED Angel Eye per Supermoto con staffe di montaggio universali e kit completo di cablaggio (coppia) - edizione limitata 2026 <b>Pro</b> & Co';

    /**
     * An order with a real product (variant + size), a deleted product line and long address lines.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function order(array $attributes = [], bool $registered = false, bool $withBillingName = true): Order
    {
        $category = Category::query()->first() ?? Category::factory()->create();
        $size = Size::factory()->create(['name' => 'M']);
        $product = Product::factory()->create(['name' => self::LONG_NAME, 'category_id' => $category->id, 'size_id' => $size->id, 'base_sku' => 'FAR-AE']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Rosso', 'sku_suffix' => '-R', 'is_default' => false]);

        $user = $registered ? User::factory()->create(['name' => 'Anna Verdi']) : null;

        $order = Order::factory()->create(array_merge([
            'user_id' => $user?->id,
            'guest_email' => $registered ? null : 'guest@example.test',
            'payment_status' => 'succeeded',
            'status' => 'processing',
            'locale' => 'it',
            'currency' => 'EUR',
            'subtotal' => 60.00,
            'shipping_amount' => 5.00,
            'discount_amount' => 0,
            'coupon_code' => null,
            'total_amount' => 65.00,
            // no stored VAT rate by default (older orders): the plain "VAT included" sentence
            'tax_amount' => 0,
            'tax_rate' => null,
            'tracking_number' => null,
            'shipping_address_id' => Address::factory()->shipping()->create([
                'first_name' => 'Maria Giovanna', 'last_name' => 'Rossi-De Santis',
                'address_line_1' => 'Via Giuseppe Garibaldi 12/B, Scala C, Interno 7, Quartiere Santa Croce, Presso Studio Legale Associato',
                'address_line_2' => null, 'state' => 'RE', 'country' => 'Italy',
            ]),
            'billing_address_id' => Address::factory()->billing()->create([
                'first_name' => $withBillingName ? 'Mario' : '', 'last_name' => $withBillingName ? 'Rossi' : '',
            ]),
        ], $attributes));

        $order->orderItems()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'size_id' => $size->id,
            'product_name' => self::LONG_NAME, 'quantity' => 2, 'price' => 25.00, 'total' => 50.00,
        ]);
        // the product was deleted after the purchase: only the stored name is left
        $order->orderItems()->create([
            'product_id' => null, 'product_name' => 'Guarnizione di ricambio', 'quantity' => 1, 'price' => 10.00, 'total' => 10.00,
        ]);

        return $order->fresh();
    }

    /**
     * Discounted variant of order(): subtotal 60 - 6 (coupon) + 5 shipping = 59.
     */
    public static function discountedOrder(array $attributes = [], bool $registered = false): Order
    {
        return self::order(array_merge(['discount_amount' => 6.00, 'coupon_code' => 'PRIMAVERA10', 'total_amount' => 59.00], $attributes), $registered);
    }

    /**
     * Sends the mailable through the (array) transport in the given locale and returns the delivered message.
     */
    public static function deliver(object $mailable, string $locale, string $to = 'someone@example.test'): Email
    {
        Mail::to($to)->locale($locale)->send($mailable);

        return self::lastDelivered();
    }

    public static function lastDelivered(): Email
    {
        $messages = collect(Mail::mailer()->getSymfonyTransport()->messages())
            ->map(fn (SentMessage $message) => $message->getOriginalMessage());

        return $messages->last();
    }
}
