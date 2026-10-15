<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\AbandonedCheckoutReminders;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin > Newsletter > Subscribers: list, unsubscribe, GDPR erase, CSV export of the confirmed list.
 */
class NewsletterSubscribersController extends Controller
{
    private const PER_PAGE = 25;

    private const STATUSES = [
        NewsletterSubscriber::STATUS_PENDING,
        NewsletterSubscriber::STATUS_CONFIRMED,
        NewsletterSubscriber::STATUS_UNSUBSCRIBED,
    ];

    public function __construct(private readonly MarketingConsent $consent) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : '';

        $subscribers = NewsletterSubscriber::query()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', '%'.mb_strtolower($search).'%'))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = NewsletterSubscriber::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('Admin/Newsletter/Subscribers', [
            'subscribers' => [
                'data' => $subscribers->getCollection()->map(fn (NewsletterSubscriber $subscriber) => [
                    'id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'status' => $subscriber->status,
                    'source' => $subscriber->source,
                    'locale' => $subscriber->locale,
                    'confirmed_at' => $subscriber->confirmed_at?->toIso8601String(),
                    'created_at' => $subscriber->created_at?->toIso8601String(),
                ])->values(),
                'meta' => [
                    'total' => $subscribers->total(),
                    'per_page' => $subscribers->perPage(),
                    'current_page' => $subscribers->currentPage(),
                    'last_page' => $subscribers->lastPage(),
                    'from' => $subscribers->firstItem(),
                    'to' => $subscribers->lastItem(),
                ],
            ],
            'counts' => [
                'pending' => (int) ($counts[NewsletterSubscriber::STATUS_PENDING] ?? 0),
                'confirmed' => (int) ($counts[NewsletterSubscriber::STATUS_CONFIRMED] ?? 0),
                'unsubscribed' => (int) ($counts[NewsletterSubscriber::STATUS_UNSUBSCRIBED] ?? 0),
                'total' => (int) $counts->sum(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'abandoned' => app(AbandonedCheckoutReminders::class)->stats(),
        ]);
    }

    public function unsubscribe(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        $this->consent->unsubscribe($subscriber, 'admin');

        return back()->with('success', __('newsletter.admin.unsubscribed_flash'));
    }

    /**
     * GDPR erasure (see MarketingConsent::erase).
     */
    public function destroy(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        $id = $subscriber->id;

        $this->consent->erase($subscriber);

        Log::info('Newsletter subscriber erased', ['subscriber_id' => $id, 'admin_id' => $request->user()?->id]);

        return back()->with('success', __('newsletter.admin.erased_flash'));
    }

    /**
     * Confirmed subscribers only (the only ones who may be mailed), streamed in chunks.
     */
    public function export(): StreamedResponse
    {
        $filename = 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'locale', 'confirmed_at', 'source']);

            NewsletterSubscriber::query()
                ->where('status', NewsletterSubscriber::STATUS_CONFIRMED)
                ->orderBy('id')
                ->chunkById(500, function ($subscribers) use ($out) {
                    foreach ($subscribers as $subscriber) {
                        fputcsv($out, [
                            self::csvSafe($subscriber->email),
                            (string) $subscriber->locale,
                            $subscriber->confirmed_at?->toIso8601String() ?? '',
                            (string) $subscriber->source,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A cell starting with = + - @ (or a control character) is a formula for spreadsheet programs: prefix a quote.
     */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
