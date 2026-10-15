<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Newsletter\DispatchCampaign;
use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Admin > Newsletter > Campaigns: compose (markdown + live preview), test send, send now / schedule, cancel, stats.
 */
class NewsletterCampaignsController extends Controller
{
    public function index(): Response
    {
        $campaigns = NewsletterCampaign::query()->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Newsletter/Campaigns/Index', [
            'campaigns' => [
                'data' => $campaigns->getCollection()->map(fn (NewsletterCampaign $campaign) => $this->summary($campaign))->values(),
                'meta' => [
                    'total' => $campaigns->total(),
                    'current_page' => $campaigns->currentPage(),
                    'last_page' => $campaigns->lastPage(),
                ],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->editor($request, null);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = NewsletterCampaign::create($this->validated($request) + [
            'status' => NewsletterCampaign::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.newsletter.campaigns.edit', $campaign)->with('success', __('newsletter.admin.campaign.saved'));
    }

    public function edit(Request $request, NewsletterCampaign $campaign): Response|RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('admin.newsletter.campaigns.show', $campaign);
        }

        return $this->editor($request, $campaign);
    }

    public function update(Request $request, NewsletterCampaign $campaign): RedirectResponse
    {
        $this->ensureEditable($campaign);

        $campaign->update($this->validated($request));

        return back()->with('success', __('newsletter.admin.campaign.saved'));
    }

    public function show(NewsletterCampaign $campaign): Response
    {
        $sends = $campaign->sends();

        return Inertia::render('Admin/Newsletter/Campaigns/Show', [
            'campaign' => $this->summary($campaign) + [
                'preheader' => $campaign->preheader,
                'body_markdown' => $campaign->body_markdown,
                'editable' => $campaign->isEditable(),
            ],
            'stats' => [
                'recipients' => $campaign->recipients_count,
                'sent' => (clone $sends)->whereNotNull('sent_at')->count(),
                'failed' => (clone $sends)->whereNotNull('failed_at')->count(),
                'skipped' => (clone $sends)->whereNotNull('skipped_at')->count(),
                'pending' => (clone $sends)->whereNull('sent_at')->whereNull('failed_at')->whereNull('skipped_at')->count(),
            ],
        ]);
    }

    /**
     * Live preview for the editor: the real mailable, rendered with the current (unsaved) fields.
     */
    public function preview(Request $request): JsonResponse
    {
        $campaign = new NewsletterCampaign($this->validated($request));

        $mail = new NewsletterCampaignMail($campaign, null, isTest: true);
        $locale = $campaign->locale ?? app()->getLocale();

        return response()->json(['html' => $mail->locale($locale)->render()]);
    }

    /**
     * Send the campaign to the logged-in admin only. Nothing about the campaign changes.
     */
    public function test(Request $request, NewsletterCampaign $campaign): RedirectResponse
    {
        $email = (string) $request->user()->email;

        try {
            Mail::to($email)
                ->locale($campaign->locale ?? app()->getLocale())
                ->send(new NewsletterCampaignMail($campaign, null, isTest: true));
        } catch (Throwable $e) {
            Log::error('Newsletter test send failed: '.$e->getMessage(), ['campaign_id' => $campaign->id]);

            return back()->with('error', __('newsletter.admin.campaign.test_failed'));
        }

        return back()->with('success', __('newsletter.admin.campaign.test_sent', ['email' => $email]));
    }

    public function send(NewsletterCampaign $campaign): RedirectResponse
    {
        $this->ensureSendable($campaign);

        $claimed = NewsletterCampaign::query()
            ->whereKey($campaign->id)
            ->whereIn('status', [NewsletterCampaign::STATUS_DRAFT, NewsletterCampaign::STATUS_SCHEDULED])
            ->update(['status' => NewsletterCampaign::STATUS_SENDING, 'started_at' => now(), 'scheduled_at' => null, 'updated_at' => now()]);

        if ($claimed === 1) {
            DispatchCampaign::dispatch($campaign->id);
        }

        return redirect()->route('admin.newsletter.campaigns.show', $campaign)->with('success', __('newsletter.admin.campaign.sending'));
    }

    public function schedule(Request $request, NewsletterCampaign $campaign): RedirectResponse
    {
        $this->ensureSendable($campaign);

        $data = $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);

        $campaign->forceFill([
            'status' => NewsletterCampaign::STATUS_SCHEDULED,
            'scheduled_at' => Carbon::parse($data['scheduled_at']),
        ])->save();

        return redirect()->route('admin.newsletter.campaigns.show', $campaign)->with('success', __('newsletter.admin.campaign.scheduled'));
    }

    /**
     * Stops a scheduled or sending campaign. Mails already handed to the mail server are not recalled.
     */
    public function cancel(NewsletterCampaign $campaign): RedirectResponse
    {
        $cancelled = NewsletterCampaign::query()
            ->whereKey($campaign->id)
            ->whereIn('status', [NewsletterCampaign::STATUS_SCHEDULED, NewsletterCampaign::STATUS_SENDING])
            ->update(['status' => NewsletterCampaign::STATUS_CANCELLED, 'finished_at' => now(), 'updated_at' => now()]);

        if ($cancelled === 0) {
            throw ValidationException::withMessages(['campaign' => __('newsletter.admin.campaign.not_cancellable')]);
        }

        return back()->with('success', __('newsletter.admin.campaign.cancelled'));
    }

    // ------------------------------------------------------------------ helpers

    private function editor(Request $request, ?NewsletterCampaign $campaign): Response
    {
        return Inertia::render('Admin/Newsletter/Campaigns/Edit', [
            'campaign' => $campaign ? $this->summary($campaign) + [
                'preheader' => $campaign->preheader,
                'body_markdown' => $campaign->body_markdown,
            ] : null,
            'recipientCounts' => $this->recipientCounts(),
            'locales' => Locales::supported(),
            'newsletterEnabled' => (bool) settings('newsletter.enabled', false),
            'adminEmail' => $request->user()->email,
        ]);
    }

    /**
     * Confirmed subscribers per locale, and in total (key "all"), for the send confirmation.
     *
     * @return array<string, int>
     */
    private function recipientCounts(): array
    {
        $byLocale = NewsletterSubscriber::query()
            ->where('status', NewsletterSubscriber::STATUS_CONFIRMED)
            ->selectRaw('locale, count(*) as aggregate')
            ->groupBy('locale')
            ->pluck('aggregate', 'locale')
            ->map(fn ($count) => (int) $count);

        $counts = ['all' => (int) $byLocale->sum()];
        foreach (Locales::supported() as $locale) {
            $counts[$locale] = $byLocale[$locale] ?? 0;
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'body_markdown' => ['required', 'string', 'max:100000'],
            'locale' => ['nullable', 'string', Rule::in(Locales::supported())],
        ]);

        return [
            'subject' => $data['subject'],
            'preheader' => ($data['preheader'] ?? '') !== '' ? $data['preheader'] : null,
            'body_markdown' => $data['body_markdown'],
            'locale' => ($data['locale'] ?? '') !== '' ? $data['locale'] : null,
        ];
    }

    private function ensureEditable(NewsletterCampaign $campaign): void
    {
        if (! $campaign->isEditable()) {
            throw ValidationException::withMessages(['campaign' => __('newsletter.admin.campaign.not_editable')]);
        }
    }

    private function ensureSendable(NewsletterCampaign $campaign): void
    {
        if (! settings('newsletter.enabled', false)) {
            throw ValidationException::withMessages(['campaign' => __('newsletter.admin.campaign.newsletter_off')]);
        }

        $this->ensureEditable($campaign);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(NewsletterCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'subject' => $campaign->subject,
            'locale' => $campaign->locale,
            'status' => $campaign->status,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'finished_at' => $campaign->finished_at?->toIso8601String(),
            'recipients_count' => $campaign->recipients_count,
            'sent_count' => $campaign->sent_count,
            'failed_count' => $campaign->failed_count,
            'created_at' => $campaign->created_at?->toIso8601String(),
        ];
    }
}
