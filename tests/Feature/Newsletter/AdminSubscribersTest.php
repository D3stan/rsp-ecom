<?php

use App\Models\ConsentEvent;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

function adminSubscriber(string $email, string $status = NewsletterSubscriber::STATUS_CONFIRMED, string $locale = 'it', string $source = 'footer'): NewsletterSubscriber
{
    $consent = app(MarketingConsent::class);
    $subscriber = $consent->subscribe($email, $source, $locale, null, '203.0.113.7', 'Test UA');

    if ($status !== NewsletterSubscriber::STATUS_PENDING) {
        $subscriber = $consent->confirm($consent->issueConfirmationToken($subscriber->fresh()));
    }

    if ($status === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
        $subscriber = $consent->unsubscribe($subscriber, 'link');
    }

    return $subscriber->fresh();
}

it('redirects guests and forbids customers on every newsletter admin route', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $subscriber = adminSubscriber('rider@example.com');

    $this->get(route('admin.newsletter.subscribers'))->assertRedirect();

    foreach ([
        fn () => $this->actingAs($customer)->get('/admin/newsletter'),
        fn () => $this->actingAs($customer)->get(route('admin.newsletter.subscribers')),
        fn () => $this->actingAs($customer)->get(route('admin.newsletter.subscribers.export')),
        fn () => $this->actingAs($customer)->post(route('admin.newsletter.subscribers.unsubscribe', $subscriber)),
        fn () => $this->actingAs($customer)->delete(route('admin.newsletter.subscribers.destroy', $subscriber)),
    ] as $request) {
        $request()->assertForbidden();
    }

    expect($subscriber->fresh()->status)->toBe(NewsletterSubscriber::STATUS_CONFIRMED);
});

it('lists subscribers with counts by status, search, status filter and pagination', function () {
    adminSubscriber('alpha@example.com');
    adminSubscriber('beta@example.com', NewsletterSubscriber::STATUS_PENDING);
    adminSubscriber('gamma@example.com', NewsletterSubscriber::STATUS_UNSUBSCRIBED);
    foreach (range(1, 30) as $i) {
        adminSubscriber("bulk{$i}@example.com");
    }

    $this->actingAs($this->admin)->get('/admin/newsletter')->assertRedirect(route('admin.newsletter.subscribers'));

    $this->actingAs($this->admin)->get(route('admin.newsletter.subscribers'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Newsletter/Subscribers')
            ->where('counts', ['pending' => 1, 'confirmed' => 31, 'unsubscribed' => 1, 'total' => 33])
            ->has('subscribers.data', 25)
            ->where('subscribers.meta.total', 33)
            ->where('subscribers.meta.last_page', 2)
            ->has('subscribers.data.0', fn (Assert $row) => $row
                ->hasAll(['id', 'email', 'status', 'source', 'locale', 'confirmed_at', 'created_at'])));

    $this->actingAs($this->admin)->get(route('admin.newsletter.subscribers', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 8)
            ->where('subscribers.meta.current_page', 2));

    $this->actingAs($this->admin)->get(route('admin.newsletter.subscribers', ['search' => 'BETA']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.email', 'beta@example.com')
            ->where('filters.search', 'BETA'));

    $this->actingAs($this->admin)->get(route('admin.newsletter.subscribers', ['status' => 'unsubscribed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.email', 'gamma@example.com')
            ->where('filters.status', 'unsubscribed'));
});

it('lets the admin unsubscribe a subscriber, logged as an admin event', function () {
    $subscriber = adminSubscriber('rider@example.com');

    $this->actingAs($this->admin)->from(route('admin.newsletter.subscribers'))
        ->post(route('admin.newsletter.subscribers.unsubscribe', $subscriber))
        ->assertRedirect(route('admin.newsletter.subscribers'))
        ->assertSessionHas('success');

    expect($subscriber->fresh()->status)->toBe(NewsletterSubscriber::STATUS_UNSUBSCRIBED)
        ->and(ConsentEvent::where('subscriber_id', $subscriber->id)->latest('id')->first()->source)->toBe('admin');
});

it('erases a subscriber everywhere and keeps only an anonymised erased event', function () {
    $user = User::factory()->create(['email' => 'rider@example.com']);
    $subscriber = adminSubscriber('rider@example.com');
    $subscriber->forceFill(['user_id' => $user->id])->save();
    $other = adminSubscriber('other@example.com');

    $this->actingAs($this->admin)->from(route('admin.newsletter.subscribers'))
        ->delete(route('admin.newsletter.subscribers.destroy', $subscriber))
        ->assertRedirect(route('admin.newsletter.subscribers'))
        ->assertSessionHas('success');

    expect(NewsletterSubscriber::find($subscriber->id))->toBeNull()
        ->and(ConsentEvent::where('subscriber_id', $subscriber->id)->count())->toBe(0)
        ->and(NewsletterSubscriber::find($other->id))->not->toBeNull()
        ->and(ConsentEvent::where('subscriber_id', $other->id)->count())->toBe(2);

    $erased = ConsentEvent::where('event', ConsentEvent::EVENT_ERASED)->sole();
    expect($erased->subscriber_id)->toBeNull()
        ->and($erased->ip)->toBeNull()
        ->and($erased->user_agent)->toBeNull()
        ->and($erased->source)->toBe('admin');

    // The address is nowhere in the newsletter tables any more (the user account itself is not touched)
    foreach (['newsletter_subscribers', 'consent_events'] as $table) {
        $rows = DB::table($table)->get()->map(fn ($row) => json_encode($row))->implode("\n");
        expect($rows)->not->toContain('rider@example.com');
    }
    expect($user->fresh())->not->toBeNull();
});

it('exports only confirmed subscribers as CSV', function () {
    adminSubscriber('confirmed@example.com', NewsletterSubscriber::STATUS_CONFIRMED, 'de', 'register');
    adminSubscriber('pending@example.com', NewsletterSubscriber::STATUS_PENDING);
    adminSubscriber('gone@example.com', NewsletterSubscriber::STATUS_UNSUBSCRIBED);
    adminSubscriber('=cmd@example.com');

    $response = $this->actingAs($this->admin)->get(route('admin.newsletter.subscribers.export'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment');

    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $response->streamedContent()))));
    $rows = array_map('str_getcsv', $lines);

    expect($rows[0])->toBe(['email', 'locale', 'confirmed_at', 'source'])
        ->and($rows)->toHaveCount(3);

    $emails = array_column(array_slice($rows, 1), 0);
    expect($emails)->toContain('confirmed@example.com')
        ->and($emails)->not->toContain('pending@example.com')
        ->and($emails)->not->toContain('gone@example.com')
        // Spreadsheet formula injection is neutralised
        ->and($emails)->toContain("'=cmd@example.com");

    $confirmed = collect($rows)->first(fn ($row) => $row[0] === 'confirmed@example.com');
    expect($confirmed[1])->toBe('de')
        ->and($confirmed[3])->toBe('register')
        ->and($confirmed[2])->not->toBe('');
});
