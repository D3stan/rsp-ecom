<?php

namespace Tests\Feature\Mail\Lifecycle\Support;

use App\Jobs\SendOrderEmail;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderEmail;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

class LifecycleFixtures
{
    /**
     * A guest order whose payment succeeded (so the customer knows about it), with one item.
     */
    public static function paidOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'user_id' => null,
            'guest_email' => 'guest@example.test',
            'guest_phone' => '+39 333 0000000',
            'payment_status' => 'succeeded',
            'status' => 'processing',
            'locale' => 'it',
            'currency' => 'EUR',
            'subtotal' => 50.00,
            'shipping_amount' => 5.00,
            'discount_amount' => 0,
            'total_amount' => 55.00,
            'shipping_address_id' => Address::factory()->shipping(),
            'billing_address_id' => Address::factory()->billing()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']),
        ], $attributes));

        $order->orderItems()->create([
            'product_id' => null,
            'product_name' => 'Maglia <b>Test</b> & Co',
            'quantity' => 2,
            'price' => 25.00,
            'total' => 50.00,
        ]);

        return $order->fresh();
    }

    /**
     * A checkout that was started but never paid.
     */
    public static function unpaidOrder(array $attributes = []): Order
    {
        return self::paidOrder(array_merge([
            'status' => 'pending',
            'payment_status' => 'processing',
        ], $attributes));
    }

    /**
     * Runs the queued job for a given order_emails row, as a worker would.
     */
    public static function runJob(OrderEmail $row): void
    {
        (new SendOrderEmail($row->order_id, $row->type, $row->fingerprint, null, $row->type === OrderEmail::TYPE_REFUNDED ? '20.00' : null))->handle();
    }

    /**
     * Messages delivered through the (array) mail transport so far.
     *
     * @return array<int, Email>
     */
    public static function delivered(): array
    {
        return collect(Mail::mailer()->getSymfonyTransport()->messages())
            ->map(fn (SentMessage $message) => $message->getOriginalMessage())
            ->values()
            ->all();
    }
}
