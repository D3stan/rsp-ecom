<?php

use App\Jobs\Newsletter\DispatchCampaign;
use App\Jobs\Newsletter\SendCampaignEmail;
use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignSend;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    settings()->set('newsletter.enabled', true);
    $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'owner@example.com']);
});

function campaignSubscriber(string $email, string $locale = 'it', string $status = NewsletterSubscriber::STATUS_CONFIRMED): NewsletterSubscriber
{
    $consent = app(MarketingConsent::class);
    $subscriber = $consent->subscribe($email, 'footer', $locale);

    if ($status !== NewsletterSubscriber::STATUS_PENDING) {
        $subscriber = $consent->confirm($consent->issueConfirmationToken($subscriber->fresh()));
    }
    if ($status === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
        $subscriber = $consent->unsubscribe($subscriber, 'link');
    }

    return $subscriber->fresh();
}

function draftCampaign(array $attributes = []): NewsletterCampaign
{
    return NewsletterCampaign::create(array_merge([
        'subject' => 'Spring offers',
        'preheader' => 'New lights are in',
        'body_markdown' => "# Hello riders\n\nOur **new** headlights are here.",
        'locale' => null,
        'status' => NewsletterCampaign::STATUS_DRAFT,
    ], $attributes));
}

/** @return array<int, string> addresses that received the campaign mail */
function campaignRecipients(): array
{
    return Mail::sent(NewsletterCampaignMail::class)
        ->flatMap(fn (NewsletterCampaignMail $mail) => collect($mail->to)->pluck('address'))
        ->values()
        ->all();
}

// ------------------------------------------------------------------ access

it('forbids customers on every campaign route', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $campaign = draftCampaign();

    foreach ([
        fn () => $this->actingAs($customer)->get(route('admin.newsletter.campaigns.index')),
        fn () => $this->actingAs($customer)->get(route('admin.newsletter.campaigns.create')),
        fn () => $this->actingAs($customer)->post(route('admin.newsletter.campaigns.store'), ['subject' => 'x', 'body_markdown' => 'y']),
        fn () => $this->actingAs($customer)->get(route('admin.newsletter.campaigns.show', $campaign)),
        fn () => $this->actingAs($customer)->get(route('admin.newsletter.campaigns.edit', $campaign)),
        fn () => $this->actingAs($customer)->put(route('admin.newsletter.campaigns.update', $campaign), ['subject' => 'x', 'body_markdown' => 'y']),
        fn () => $this->actingAs($customer)->post(route('admin.newsletter.campaigns.preview'), ['subject' => 'x', 'body_markdown' => 'y']),
        fn () => $this->actingAs($customer)->post(route('admin.newsletter.campaigns.test', $campaign)),
        fn () => $this->actingAs($customer)->post(route('admin.newsletter.campaigns.send', $campaign)),
        fn () => $this->actingAs($customer)->post(route('admin.newsletter.campaigns.schedule', $campaign), ['scheduled_at' => now()->addDay()->toIso8601String()]),
        fn () => $this->actingAs($customer)->post(route('admin.newsletter.campaigns.cancel', $campaign)),
    ] as $request) {
        $request()->assertForbidden();
    }

    expect(NewsletterCampaign::count())->toBe(1);
    Mail::assertNothingSent();
});

// --------------------------------------------------------------- compose

it('creates and edits a draft campaign with validation', function () {
    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.store'), ['subject' => '', 'body_markdown' => ''])
        ->assertSessionHasErrors(['subject', 'body_markdown']);
    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.store'), ['subject' => 'S', 'body_markdown' => 'B', 'locale' => 'xx'])
        ->assertSessionHasErrors('locale');

    $response = $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.store'), [
        'subject' => 'Spring offers',
        'preheader' => 'New lights',
        'body_markdown' => 'Hello **riders**',
        'locale' => 'it',
    ]);

    $campaign = NewsletterCampaign::sole();
    $response->assertRedirect(route('admin.newsletter.campaigns.edit', $campaign));
    expect($campaign->status)->toBe(NewsletterCampaign::STATUS_DRAFT)
        ->and($campaign->created_by)->toBe($this->admin->id)
        ->and($campaign->locale)->toBe('it');

    $this->actingAs($this->admin)->put(route('admin.newsletter.campaigns.update', $campaign), [
        'subject' => 'Summer offers',
        'body_markdown' => 'Changed',
        'locale' => '',
    ])->assertRedirect();

    expect($campaign->fresh()->subject)->toBe('Summer offers')
        ->and($campaign->fresh()->locale)->toBeNull();

    $this->actingAs($this->admin)->get(route('admin.newsletter.campaigns.edit', $campaign))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Newsletter/Campaigns/Edit')
            ->where('campaign.subject', 'Summer offers')
            ->has('recipientCounts'));

    $this->actingAs($this->admin)->get(route('admin.newsletter.campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Newsletter/Campaigns/Index')
            ->has('campaigns.data', 1));
});

it('previews the markdown through the email renderer, without raw HTML', function () {
    $response = $this->actingAs($this->admin)->postJson(route('admin.newsletter.campaigns.preview'), [
        'subject' => 'Spring offers',
        'preheader' => 'Preview text',
        'body_markdown' => "Our **new** lights\n\n<script>alert(1)</script>",
    ])->assertOk();

    $html = $response->json('html');
    expect($html)->toContain('<strong')
        ->and($html)->toContain('new</strong>')
        ->and($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('Preview text');
});

it('only edits campaigns that have not started sending', function () {
    $campaign = draftCampaign(['status' => NewsletterCampaign::STATUS_SENT]);

    $this->actingAs($this->admin)->put(route('admin.newsletter.campaigns.update', $campaign), ['subject' => 'x', 'body_markdown' => 'y'])
        ->assertSessionHasErrors('campaign');

    expect($campaign->fresh()->subject)->toBe('Spring offers');
});

// ------------------------------------------------------------------- test

it('sends a test only to the admin, without touching the campaign', function () {
    campaignSubscriber('rider@example.com');
    $campaign = draftCampaign();

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.test', $campaign))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(campaignRecipients())->toBe(['owner@example.com'])
        ->and($campaign->fresh()->status)->toBe(NewsletterCampaign::STATUS_DRAFT)
        ->and(NewsletterCampaignSend::count())->toBe(0);
});

// ------------------------------------------------------------------- send

it('sends only to confirmed subscribers of the campaign locale', function () {
    campaignSubscriber('it-confirmed@example.com', 'it');
    campaignSubscriber('de-confirmed@example.com', 'de');
    campaignSubscriber('it-pending@example.com', 'it', NewsletterSubscriber::STATUS_PENDING);
    campaignSubscriber('it-gone@example.com', 'it', NewsletterSubscriber::STATUS_UNSUBSCRIBED);
    $campaign = draftCampaign(['locale' => 'it']);

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign))
        ->assertRedirect(route('admin.newsletter.campaigns.show', $campaign));

    expect(campaignRecipients())->toBe(['it-confirmed@example.com']);

    $campaign->refresh();
    expect($campaign->status)->toBe(NewsletterCampaign::STATUS_SENT)
        ->and($campaign->recipients_count)->toBe(1)
        ->and($campaign->sent_count)->toBe(1)
        ->and($campaign->failed_count)->toBe(0)
        ->and($campaign->started_at)->not->toBeNull()
        ->and($campaign->finished_at)->not->toBeNull();

    $this->actingAs($this->admin)->get(route('admin.newsletter.campaigns.show', $campaign))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Newsletter/Campaigns/Show')
            ->where('campaign.status', 'sent')
            ->where('stats.sent', 1));
});

it('sends to every confirmed subscriber when the campaign has no locale', function () {
    campaignSubscriber('a@example.com', 'it');
    campaignSubscriber('b@example.com', 'de');
    $campaign = draftCampaign();

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign));

    expect(campaignRecipients())->toEqualCanonicalizing(['a@example.com', 'b@example.com']);
});

it('skips subscribers who unsubscribe while the campaign is sending', function () {
    $stays = campaignSubscriber('stays@example.com');
    $leaves = campaignSubscriber('leaves@example.com');
    $campaign = draftCampaign();

    Queue::fake([SendCampaignEmail::class]);
    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign));

    expect(NewsletterCampaignSend::count())->toBe(2);
    Queue::assertPushed(SendCampaignEmail::class, 2);

    app(MarketingConsent::class)->unsubscribe($leaves, 'one_click');

    Queue::pushed(SendCampaignEmail::class)->each(fn (SendCampaignEmail $job) => app()->call([$job, 'handle']));

    expect(campaignRecipients())->toBe(['stays@example.com'])
        ->and(NewsletterCampaignSend::where('subscriber_id', $leaves->id)->sole()->skipped_at)->not->toBeNull()
        ->and($campaign->fresh()->status)->toBe(NewsletterCampaign::STATUS_SENT)
        ->and($campaign->fresh()->sent_count)->toBe(1);
});

it('never sends twice when the campaign or a send job is dispatched again', function () {
    campaignSubscriber('a@example.com');
    campaignSubscriber('b@example.com');
    $campaign = draftCampaign();

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign));
    expect(campaignRecipients())->toHaveCount(2);

    // Re-dispatch the whole campaign and replay a send job
    $campaign->forceFill(['status' => NewsletterCampaign::STATUS_SENDING])->save();
    DispatchCampaign::dispatch($campaign->id);
    app()->call([new SendCampaignEmail(NewsletterCampaignSend::first()->id), 'handle']);

    expect(campaignRecipients())->toHaveCount(2)
        ->and(NewsletterCampaignSend::count())->toBe(2);
});

it('refuses to send while the newsletter is switched off, or twice', function () {
    campaignSubscriber('a@example.com');
    $campaign = draftCampaign();

    settings()->set('newsletter.enabled', false);
    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign))->assertSessionHasErrors('campaign');
    expect($campaign->fresh()->status)->toBe(NewsletterCampaign::STATUS_DRAFT);

    settings()->set('newsletter.enabled', true);
    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign));
    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign))->assertSessionHasErrors('campaign');

    expect(campaignRecipients())->toHaveCount(1);
});

it('cancels a campaign while it is sending', function () {
    campaignSubscriber('a@example.com');
    campaignSubscriber('b@example.com');
    $campaign = draftCampaign();

    Queue::fake([SendCampaignEmail::class]);
    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign));

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.cancel', $campaign))->assertRedirect();
    expect($campaign->fresh()->status)->toBe(NewsletterCampaign::STATUS_CANCELLED);

    Queue::pushed(SendCampaignEmail::class)->each(fn (SendCampaignEmail $job) => app()->call([$job, 'handle']));

    expect(campaignRecipients())->toBe([]);
});

// --------------------------------------------------------------- schedule

it('sends a scheduled campaign from the scheduler tick once it is due', function () {
    campaignSubscriber('a@example.com');
    $campaign = draftCampaign();

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.schedule', $campaign), [
        'scheduled_at' => now()->addHours(2)->toIso8601String(),
    ])->assertRedirect();

    expect($campaign->fresh()->status)->toBe(NewsletterCampaign::STATUS_SCHEDULED);

    $this->artisan('schedule:run');
    expect(campaignRecipients())->toBe([]);

    $this->travel(2)->hours();
    $this->travel(1)->minutes();
    $this->artisan('schedule:run');

    expect(campaignRecipients())->toBe(['a@example.com'])
        ->and($campaign->fresh()->status)->toBe(NewsletterCampaign::STATUS_SENT);
});

it('refuses a schedule in the past and can cancel a scheduled campaign', function () {
    $campaign = draftCampaign();

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.schedule', $campaign), [
        'scheduled_at' => now()->subHour()->toIso8601String(),
    ])->assertSessionHasErrors('scheduled_at');

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.schedule', $campaign), [
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ]);
    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.cancel', $campaign));

    expect($campaign->fresh()->status)->toBe(NewsletterCampaign::STATUS_CANCELLED);
});

// ------------------------------------------------------------------ email

it('carries one-click unsubscribe headers, a visible unsubscribe link and the sender identity', function () {
    settings()->set('store.legal_name', 'Moto Luci S.r.l.');
    settings()->set('store.address', 'Via Roma 1, Milano');
    $subscriber = campaignSubscriber('a@example.com', 'de');
    $campaign = draftCampaign();

    $this->actingAs($this->admin)->post(route('admin.newsletter.campaigns.send', $campaign));

    $mail = Mail::sent(NewsletterCampaignMail::class)->sole();
    $headers = $mail->headers()->text;

    expect($headers['List-Unsubscribe'])->toBe('<'.$subscriber->unsubscribeUrl().'>')
        ->and($headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click')
        ->and($mail->locale)->toBe('de');

    $html = $mail->render();
    expect($html)->toContain(e($subscriber->unsubscribeUrl()))
        ->and($html)->toContain('Moto Luci S.r.l.')
        ->and($html)->toContain('Via Roma 1, Milano')
        ->and($html)->toContain('Hello riders');

    $mail->assertHasSubject('Spring offers');
});
