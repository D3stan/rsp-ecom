<?php

use App\Jobs\SendOrderEmail;
use App\Mail\OrderConfirmation;
use App\Mail\OrderShipped;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

function makeOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'user_id' => null,
        'guest_email' => 'guest@example.test',
        'payment_status' => 'succeeded',
        'status' => 'processing',
    ], $attributes));
}

function runJob(OrderEmail|array $row, ?string $tracking = null): void
{
    $row = $row instanceof OrderEmail ? $row : OrderEmail::where($row)->firstOrFail();

    (new SendOrderEmail($row->order_id, $row->type, $row->fingerprint, $tracking))->handle();
}

it('queues the confirmation once and records the claim', function () {
    Queue::fake();
    $order = makeOrder();
    $service = app(EmailService::class);

    expect($service->sendOrderConfirmation($order))->toBeTrue();
    expect($service->sendOrderConfirmation($order))->toBeTrue(); // replay / second path

    Queue::assertPushed(SendOrderEmail::class, 1);
    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->orderId === $order->id
        && $job->type === OrderEmail::TYPE_CONFIRMATION
        && $job->afterCommit === true);
    expect(OrderEmail::where('order_id', $order->id)->count())->toBe(1);
    expect(OrderEmail::first()->queued_at)->not->toBeNull()->and(OrderEmail::first()->sent_at)->toBeNull();
});

it('sends no mail while queueing, only when the job runs', function () {
    Queue::fake();
    Mail::fake();
    $order = makeOrder();

    app(EmailService::class)->sendOrderConfirmation($order);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('job sends the confirmation, then marks sent_at and the legacy flag', function () {
    Queue::fake();
    Mail::fake();
    $order = makeOrder();
    app(EmailService::class)->sendOrderConfirmation($order);

    runJob(OrderEmail::first());

    Mail::assertSent(OrderConfirmation::class, fn ($mail) => $mail->hasTo('guest@example.test'));
    $row = OrderEmail::first();
    expect($row->sent_at)->not->toBeNull()->and($row->error)->toBeNull();
    expect($order->fresh()->confirmation_email_sent)->toBeTrue();
});

it('job is idempotent: running it twice sends one mail', function () {
    Queue::fake();
    Mail::fake();
    $order = makeOrder();
    app(EmailService::class)->sendOrderConfirmation($order);

    runJob(OrderEmail::first());
    runJob(OrderEmail::first());

    Mail::assertSent(OrderConfirmation::class, 1);
});

it('does not queue again once the confirmation was sent', function () {
    Queue::fake();
    Mail::fake();
    $order = makeOrder();
    $service = app(EmailService::class);
    $service->sendOrderConfirmation($order);
    runJob(OrderEmail::first());

    expect($service->sendOrderConfirmation($order->fresh()))->toBeTrue();

    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('treats an order flagged by the legacy column as already sent', function () {
    Queue::fake();
    $order = makeOrder(['confirmation_email_sent' => true, 'confirmation_email_sent_at' => now()]);

    expect(app(EmailService::class)->sendOrderConfirmation($order))->toBeTrue();

    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);
});

it('sends end to end on the sync queue, using the configured no-reply sender', function () {
    config(['mail.from.address' => 'noreply@shop.test', 'mail.from.name' => 'Shop']);
    $order = makeOrder();

    expect(app(EmailService::class)->sendOrderConfirmation($order))->toBeTrue();

    $messages = app('mailer')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);
    $email = $messages[0]->getOriginalMessage();
    expect($email->getFrom()[0]->getAddress())->toBe('noreply@shop.test');
    expect($email->getReplyTo())->toBe([]);
    expect($email->getTo()[0]->getAddress())->toBe('guest@example.test');
    expect(OrderEmail::first()->sent_at)->not->toBeNull();
});

it('a failed send keeps the claim, records the error and retries; final failure marks failed_at', function () {
    Queue::fake();
    $order = makeOrder();
    app(EmailService::class)->sendOrderConfirmation($order);
    $row = OrderEmail::first();

    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    expect(fn () => runJob($row))->toThrow(RuntimeException::class);
    $row->refresh();
    expect($row->sent_at)->toBeNull()->and($row->failed_at)->toBeNull();
    expect($row->error)->toContain('smtp down');

    $job = new SendOrderEmail($order->id, $row->type, $row->fingerprint);
    $job->failed(new RuntimeException('smtp down'));
    $row->refresh();
    expect($row->failed_at)->not->toBeNull()->and($row->sent_at)->toBeNull();
});

it('a permanently failed email is not re-queued automatically but can be retried explicitly', function () {
    Queue::fake();
    $order = makeOrder();
    $service = app(EmailService::class);
    $service->sendOrderConfirmation($order);
    OrderEmail::first()->forceFill(['failed_at' => now(), 'error' => 'boom'])->save();

    expect($service->sendOrderConfirmation($order))->toBeFalse();
    Queue::assertPushed(SendOrderEmail::class, 1);

    expect($service->sendOrderConfirmation($order, retryFailed: true))->toBeTrue();
    Queue::assertPushed(SendOrderEmail::class, 2);
    expect(OrderEmail::first()->failed_at)->toBeNull()->and(OrderEmail::first()->error)->toBeNull();
    expect(OrderEmail::count())->toBe(1);
});

it('re-dispatches a claim whose job was lost (queued long ago, never sent)', function () {
    Queue::fake();
    $order = makeOrder();
    $service = app(EmailService::class);
    $service->sendOrderConfirmation($order);

    $service->sendOrderConfirmation($order); // fresh claim: no second job
    Queue::assertPushed(SendOrderEmail::class, 1);

    OrderEmail::first()->forceFill(['queued_at' => now()->subHours(4)])->save();
    $service->sendOrderConfirmation($order);

    Queue::assertPushed(SendOrderEmail::class, 2);
    expect(OrderEmail::first()->queued_at->isAfter(now()->subMinute()))->toBeTrue();
});

it('does not record a claim when the queue is unavailable, so a later call can succeed', function () {
    $order = makeOrder();
    Queue::shouldReceive('push')->andThrow(new RuntimeException('queue down'));
    Queue::shouldReceive('later')->andThrow(new RuntimeException('queue down'));

    expect(app(EmailService::class)->sendOrderConfirmation($order))->toBeFalse();
    expect(OrderEmail::count())->toBe(0);
});

it('returns false without a claim when the order has no recipient', function () {
    Queue::fake();
    $order = makeOrder(['guest_email' => null]);

    expect(app(EmailService::class)->sendOrderConfirmation($order))->toBeFalse();

    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);
});

it('the job gives up without retrying when the order has no recipient', function () {
    Queue::fake();
    Mail::fake();
    $order = makeOrder();
    app(EmailService::class)->sendOrderConfirmation($order);
    $order->update(['guest_email' => null]);

    runJob(OrderEmail::first());

    Mail::assertNothingSent();
    expect(OrderEmail::first()->failed_at)->not->toBeNull();
});

it('uses the registered user email and survives a deleted user or product', function () {
    Queue::fake();
    Mail::fake();
    $user = User::factory()->create(['email' => 'member@example.test']);
    $order = makeOrder(['user_id' => $user->id, 'guest_email' => null]);
    app(EmailService::class)->sendOrderConfirmation($order);

    runJob(OrderEmail::first());
    Mail::assertSent(OrderConfirmation::class, fn ($mail) => $mail->hasTo('member@example.test'));

    // order deleted before the job runs: nothing happens, nothing throws
    $order2 = makeOrder();
    app(EmailService::class)->sendOrderConfirmation($order2);
    $row2 = OrderEmail::where('order_id', $order2->id)->first();
    $order2->delete();
    (new SendOrderEmail($row2->order_id, $row2->type, $row2->fingerprint))->handle();
    expect(OrderEmail::where('order_id', $order2->id)->count())->toBe(0);
});

it('applies the order locale to the mailable when the column exists', function () {
    if (! Schema::hasColumn('orders', 'locale')) {
        $this->markTestSkipped('orders.locale not present yet');
    }

    Queue::fake();
    Mail::fake();
    $order = makeOrder(['locale' => 'de']);
    app(EmailService::class)->sendOrderConfirmation($order);

    runJob(OrderEmail::first());

    Mail::assertSent(OrderConfirmation::class, fn ($mail) => $mail->locale === 'de');
});

it('falls back to the app locale for unknown order locales', function () {
    if (! Schema::hasColumn('orders', 'locale')) {
        $this->markTestSkipped('orders.locale not present yet');
    }

    Queue::fake();
    Mail::fake();
    config(['app.default_locale' => 'it']);
    $order = makeOrder(['locale' => 'xx']);
    app(EmailService::class)->sendOrderConfirmation($order);

    runJob(OrderEmail::first());

    Mail::assertSent(OrderConfirmation::class, fn ($mail) => $mail->locale === 'it');
});

it('honors the master and per-type settings and defaults to enabled', function () {
    Queue::fake();
    $service = app(EmailService::class);

    expect($service->isEnabled(OrderEmail::TYPE_CONFIRMATION))->toBeTrue();
    expect($service->isEnabled(OrderEmail::TYPE_SHIPPED))->toBeTrue();

    settings()->set('email.order_confirmation', false);
    $order = makeOrder();
    expect($service->sendOrderConfirmation($order))->toBeTrue();
    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);
    expect($service->isEnabled(OrderEmail::TYPE_SHIPPED))->toBeTrue();

    settings()->set('email.order_confirmation', true);
    settings()->set('email.enabled', false);
    expect($service->isEnabled(OrderEmail::TYPE_SHIPPED))->toBeFalse();
    $service->sendOrderShipped($order, 'TRK1');
    Queue::assertNothingPushed();

    settings()->set('email.enabled', true);
    settings()->set('email.order_shipped', false);
    $service->sendOrderShipped($order, 'TRK1');
    Queue::assertNothingPushed();
});

it('sends the shipped email once per tracking number', function () {
    Queue::fake();
    Mail::fake();
    $order = makeOrder(['status' => 'shipped']);
    $service = app(EmailService::class);

    $service->sendOrderShipped($order, null);
    $service->sendOrderShipped($order, null);      // repeated save
    $service->sendOrderShipped($order, 'TRK123');
    $service->sendOrderShipped($order, ' TRK123 '); // same number
    $service->sendOrderShipped($order, 'TRK999');  // corrected number

    Queue::assertPushed(SendOrderEmail::class, 3);
    expect(OrderEmail::where('type', OrderEmail::TYPE_SHIPPED)->count())->toBe(3);

    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->trackingNumber === 'TRK123');

    runJob(OrderEmail::where('fingerprint', OrderEmail::shippedFingerprint('TRK123'))->first(), 'TRK123');
    Mail::assertSent(OrderShipped::class, fn ($mail) => $mail->trackingNumber === 'TRK123' && $mail->hasTo('guest@example.test'));
});

it('does not touch the confirmation legacy flag when a shipped email is sent', function () {
    Queue::fake();
    Mail::fake();
    $order = makeOrder(['status' => 'shipped']);
    app(EmailService::class)->sendOrderShipped($order, 'T1');

    runJob(OrderEmail::first(), 'T1');

    expect($order->fresh()->confirmation_email_sent)->toBeFalse();
});

it('two concurrent claims for the same email produce a single row', function () {
    Queue::fake();
    $order = makeOrder();
    $service = app(EmailService::class);

    // Simulate the other process winning the race between our check and our insert.
    OrderEmail::create([
        'order_id' => $order->id, 'type' => OrderEmail::TYPE_CONFIRMATION,
        'fingerprint' => 'default', 'queued_at' => now(),
    ]);

    expect($service->sendOrderConfirmation($order))->toBeTrue();
    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(1);
});

it('exposes the email rows through Order::emails() and hasEmailBeenSent()', function () {
    Queue::fake();
    Mail::fake();
    $order = makeOrder();
    app(EmailService::class)->sendOrderConfirmation($order);

    expect($order->emails)->toHaveCount(1);
    expect($order->hasEmailBeenSent())->toBeFalse();

    runJob(OrderEmail::first());

    expect($order->fresh()->hasEmailBeenSent())->toBeTrue();
});

it('cascades order_emails when the order is deleted', function () {
    Queue::fake();
    $order = makeOrder();
    app(EmailService::class)->sendOrderConfirmation($order);

    $order->delete();

    expect(OrderEmail::count())->toBe(0);
});

it('still sends when an ordered product has been deleted since', function () {
    Queue::fake();
    $order = makeOrder();
    $size = \App\Models\Size::factory()->create();
    $product = \App\Models\Product::factory()->create([
        'category_id' => \App\Models\Category::factory()->create()->id,
        'size_id' => $size->id,
    ]);
    \App\Models\OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'size_id' => $size->id]);
    app(EmailService::class)->sendOrderConfirmation($order);
    $product->delete(); // order_items.product_id is set to null by the FK

    runJob(OrderEmail::first());

    expect(OrderEmail::first()->sent_at)->not->toBeNull();
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});
