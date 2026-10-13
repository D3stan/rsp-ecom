<?php

use App\Jobs\SendOrderEmail;
use App\Jobs\SendWelcomeEmail;
use App\Mail\WelcomeEmail;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\PendingUserVerification;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PendingUserEmailVerification;
use App\Providers\AppServiceProvider;
use App\Services\EmailService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

function pendingOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'user_id' => null,
        'guest_email' => 'guest@example.test',
        'payment_status' => 'succeeded',
        'status' => 'processing',
        'confirmation_email_sent' => false,
    ], $attributes));
}

// --- orders:send-pending-emails ---------------------------------------------------------------

it('pending-emails --dry-run queues nothing', function () {
    Queue::fake();
    pendingOrder();

    $this->artisan('orders:send-pending-emails', ['--dry-run' => true])->assertSuccessful();

    Queue::assertNothingPushed();
});

it('pending-emails --force runs without any prompt and queues through the idempotent service', function () {
    Queue::fake();
    $order = pendingOrder();

    $this->artisan('orders:send-pending-emails', ['--force' => true])->assertSuccessful();
    $this->artisan('orders:send-pending-emails', ['--force' => true])->assertSuccessful(); // second run: nothing new

    Queue::assertPushed(SendOrderEmail::class, 1);
    expect(OrderEmail::where('order_id', $order->id)->count())->toBe(1);
});

it('pending-emails ignores old, unpaid, already sent and permanently failed orders', function () {
    Queue::fake();
    pendingOrder(['created_at' => now()->subDays(8)]);
    pendingOrder(['payment_status' => 'pending']);
    pendingOrder(['confirmation_email_sent' => true]);
    $failed = pendingOrder();
    OrderEmail::create([
        'order_id' => $failed->id, 'type' => OrderEmail::TYPE_CONFIRMATION, 'fingerprint' => 'default',
        'queued_at' => now()->subDay(), 'failed_at' => now(), 'error' => 'x',
    ]);

    $this->artisan('orders:send-pending-emails', ['--force' => true])->assertSuccessful();

    Queue::assertNothingPushed();

    $this->artisan('orders:send-pending-emails', ['--force' => true, '--retry-failed' => true])->assertSuccessful();
    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('pending-emails does nothing when confirmation emails are disabled', function () {
    Queue::fake();
    pendingOrder();
    Setting::set('order_confirmation_enabled', false, 'boolean');

    $this->artisan('orders:send-pending-emails', ['--force' => true])->assertSuccessful();

    Queue::assertNothingPushed();
});

// --- scheduler ---------------------------------------------------------------------------------

it('registers every scheduled task', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command);

    foreach (['orders:send-pending-emails --force', 'auth:cleanup-pending --force', 'queue:prune-failed --hours=168', 'generate:sitemap-index', 'generate:sitemap'] as $command) {
        expect($events->contains(fn ($c) => str_contains($c, $command)))->toBeTrue("missing schedule entry: {$command}");
    }

    $pending = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'orders:send-pending-emails'));
    expect($pending->expression)->toBe('*/10 * * * *')->and($pending->withoutOverlapping)->toBeTrue();
});

it('the commands it schedules exist with non-interactive flags', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKeys(['orders:send-pending-emails', 'auth:cleanup-pending', 'queue:prune-failed']);
    expect($commands['orders:send-pending-emails']->getDefinition()->hasOption('force'))->toBeTrue();
    expect($commands['auth:cleanup-pending']->getDefinition()->hasOption('force'))->toBeTrue();
});

it('no inert console kernel remains', function () {
    expect(file_exists(app_path('Console/Kernel.php')))->toBeFalse();
});

// --- welcome email / verification notification -----------------------------------------------

it('queues the welcome email and sends it from the job', function () {
    Queue::fake();
    Mail::fake();
    $user = User::factory()->create();

    expect(app(EmailService::class)->sendWelcomeEmail($user))->toBeTrue();
    Queue::assertPushed(SendWelcomeEmail::class, fn ($job) => $job->userId === $user->id && $job->afterCommit === true);
    Mail::assertNothingSent();

    (new SendWelcomeEmail($user->id))->handle();
    Mail::assertSent(WelcomeEmail::class, fn ($m) => $m->hasTo($user->email));
});

it('the welcome job is a no-op for a user deleted in the meantime', function () {
    Mail::fake();
    $user = User::factory()->create();
    $id = $user->id;
    $user->delete();

    (new SendWelcomeEmail($id))->handle();

    Mail::assertNothingSent();
});

it('the welcome email respects the master notification switch', function () {
    Queue::fake();
    Setting::set('email_notifications_enabled', false, 'boolean');

    app(EmailService::class)->sendWelcomeEmail(User::factory()->create());

    Queue::assertNothingPushed();
});

it('the verification notification is queued after commit and skips deleted records', function () {
    $pending = PendingUserVerification::factory()->create();
    $notification = new PendingUserEmailVerification($pending);

    expect($notification)->toBeInstanceOf(ShouldQueue::class);
    expect($notification->afterCommit)->toBeTrue();
    expect($notification->deleteWhenMissingModels)->toBeTrue();
    expect($notification->backoff())->toBe([60, 300, 900, 3600]);
});

it('the verification notification still delivers on the sync queue', function () {
    $pending = PendingUserVerification::factory()->create(['email' => 'new@example.test']);

    \Illuminate\Support\Facades\Notification::route('mail', $pending->email)
        ->notify(new PendingUserEmailVerification($pending));

    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});

// --- config / templates ---------------------------------------------------------------------

it('mailables carry no explicit from, so the configured no-reply address is used', function () {
    foreach ([
        new \App\Mail\OrderConfirmation(pendingOrder()),
        new \App\Mail\OrderShipped(pendingOrder(), 'T'),
        new \App\Mail\WelcomeEmail(User::factory()->create()),
        new \App\Mail\TestEmail,
    ] as $mailable) {
        expect($mailable->envelope()->from)->toBeNull();
    }
});

it('exposes a support address and never falls back to the log or array mailer', function () {
    expect(config('mail.support_address'))->toBeString()->not->toBeEmpty();
    expect(config('mail.mailers.failover.mailers'))->not->toContain('log')->not->toContain('array');
});

it('renders the no-reply notice partial standalone (html and text)', function () {
    config(['mail.support_address' => 'help@shop.test']);

    $html = view('emails.partials.no-reply-notice')->render();
    $text = view('emails.partials.no-reply-notice-text')->render();

    foreach ([$html, $text] as $output) {
        expect($output)->toContain('help@shop.test')->toContain(route('contact'))->not->toContain('emails.no_reply');
    }
    expect($text)->not->toContain('<');
});

it('ships the notice translations for it and en', function () {
    foreach (['it', 'en'] as $locale) {
        $strings = json_decode(file_get_contents(lang_path("{$locale}.json")), true);

        foreach (['title', 'body', 'contact_link'] as $key) {
            expect($strings["emails.no_reply.{$key}"] ?? '')->not->toBeEmpty();
        }
    }
});

// --- production guard ------------------------------------------------------------------------

it('logs a critical warning at boot when production uses the log mailer', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['mail.default' => 'log', 'mail.from.address' => 'noreply@shop.test', 'mail.support_address' => 'help@shop.test']);
    Log::shouldReceive('critical')->once()->withArgs(fn ($m) => str_contains($m, 'MAIL_MAILER'));

    (new AppServiceProvider(app()))->boot();
});

it('stays quiet outside production and with a safe production config', function () {
    Log::shouldReceive('critical')->never();

    config(['mail.default' => 'log']);
    (new AppServiceProvider(app()))->boot(); // testing env

    app()->detectEnvironment(fn () => 'production');
    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@shop.test', 'mail.support_address' => 'help@shop.test']);
    (new AppServiceProvider(app()))->boot();
});

// --- migration backfill ---------------------------------------------------------------------

it('backfills order_emails from the legacy confirmation flags', function () {
    $sent = pendingOrder(['confirmation_email_sent' => true, 'confirmation_email_sent_at' => now()->subDay()]);
    $unsent = pendingOrder();
    Schema::dropIfExists('order_emails');

    $migration = require database_path('migrations/2026_10_13_110000_create_order_emails_table.php');
    $migration->up();

    expect(Schema::hasTable('order_emails'))->toBeTrue();
    $rows = DB::table('order_emails')->get();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->order_id)->toBe($sent->id)
        ->and($rows[0]->type)->toBe('order_confirmation')
        ->and($rows[0]->sent_at)->not->toBeNull();
    expect(DB::table('order_emails')->where('order_id', $unsent->id)->exists())->toBeFalse();

    // the unique key blocks a second row for the same order/type/fingerprint
    expect(fn () => DB::table('order_emails')->insert([
        'order_id' => $sent->id, 'type' => 'order_confirmation', 'fingerprint' => 'default',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    // down + up are reversible
    $migration->down();
    expect(Schema::hasTable('order_emails'))->toBeFalse();
    $migration->up();
});

// --- real database queue round trip ---------------------------------------------------------

it('delivers through a real database queue worker, once, even if the claim is dispatched twice', function () {
    config(['queue.default' => 'database']);
    $order = pendingOrder();
    $service = app(EmailService::class);

    expect($service->sendOrderConfirmation($order))->toBeTrue();
    expect(DB::table('jobs')->count())->toBe(1);          // queued, nothing sent yet
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);

    // a lost-job style duplicate of the very same claim
    SendOrderEmail::dispatch($order->id, OrderEmail::TYPE_CONFIRMATION, 'default');
    expect(DB::table('jobs')->count())->toBe(2);

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1, '--sleep' => 0]);

    expect(DB::table('jobs')->count())->toBe(0);
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
    expect(OrderEmail::first()->sent_at)->not->toBeNull();
    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('pending-emails says why it skips when disabled, and leaves no rows or flags behind', function () {
    Queue::fake();
    $order = pendingOrder();
    Setting::set('email_notifications_enabled', false, 'boolean');

    $this->artisan('orders:send-pending-emails', ['--force' => true])
        ->expectsOutputToContain('disabled in the settings')
        ->assertSuccessful();
    $this->artisan('orders:send-pending-emails', ['--force' => true])->assertSuccessful();

    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0)->and($order->fresh()->confirmation_email_sent)->toBeFalse();

    // re-enabled: the 7-day window is picked up
    Setting::set('email_notifications_enabled', true, 'boolean');
    $this->artisan('orders:send-pending-emails', ['--force' => true])->assertSuccessful();
    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('pending-emails warns before flushing a large backlog', function () {
    Queue::fake();
    Order::factory()->count(26)->create([
        'user_id' => null, 'guest_email' => 'guest@example.test', 'payment_status' => 'succeeded',
        'status' => 'processing', 'confirmation_email_sent' => false,
    ]);

    $this->artisan('orders:send-pending-emails', ['--force' => true])
        ->expectsOutputToContain('Large backlog: 26 orders')
        ->assertSuccessful();

    Queue::assertPushed(SendOrderEmail::class, 26);
});
