<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\MarketingConsent;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public newsletter endpoints: sign-up (footer form), double opt-in confirmation and unsubscribe.
 * Sign-up and confirmation exist only while newsletter.enabled is on; unsubscribing always works.
 */
class NewsletterController extends Controller
{
    /** Hidden form field real visitors never fill in. */
    public const HONEYPOT_FIELD = 'website';

    public function __construct(private readonly MarketingConsent $consent) {}

    /**
     * Same answer whatever happened (new, pending, already confirmed, bot), so nobody can learn which
     * addresses are subscribed.
     */
    public function subscribe(Request $request): RedirectResponse
    {
        self::ensureEnabled();

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'in:footer'],
            self::HONEYPOT_FIELD => ['nullable', 'string', 'max:255'],
        ]);

        if (filled($data[self::HONEYPOT_FIELD] ?? null)) {
            return back()->with('success', __('newsletter.check_inbox'));
        }

        // Link the account only when the visitor subscribes their own address
        $user = $request->user();
        if ($user !== null && NewsletterSubscriber::normalizeEmail($user->email) !== NewsletterSubscriber::normalizeEmail($data['email'])) {
            $user = null;
        }

        $this->consent->subscribe($data['email'], 'footer', app()->getLocale(), $user, $request->ip(), $request->userAgent());

        return back()->with('success', __('newsletter.check_inbox'));
    }

    /**
     * Target of the link in the double opt-in mail: a page with a confirm button. Opening it changes nothing,
     * so mail scanners that prefetch links cannot confirm on the person's behalf.
     */
    public function showConfirm(Request $request, string $token): Response
    {
        self::ensureEnabled();

        return $this->confirmPage($request, $this->consent->pendingForToken($token) !== null ? 'pending' : 'invalid');
    }

    /**
     * The confirm button (CSRF-protected): only this confirms, and its IP / user agent go into the consent proof.
     */
    public function confirm(Request $request, string $token): Response
    {
        self::ensureEnabled();

        $subscriber = $this->consent->confirm($token, $request->ip(), $request->userAgent());

        return $this->confirmPage($request, $subscriber !== null ? 'confirmed' : 'invalid');
    }

    /**
     * Signed link from every marketing email: a page with a confirm button.
     */
    public function showUnsubscribe(Request $request, NewsletterSubscriber $subscriber): Response
    {
        return $this->unsubscribePage($request, $subscriber, $subscriber->status === NewsletterSubscriber::STATUS_UNSUBSCRIBED);
    }

    /**
     * The page's button and the RFC 8058 one-click target (mail clients POST "List-Unsubscribe=One-Click"
     * without cookies or CSRF token; the route is CSRF-exempt and protected by the signature). Answers 200.
     */
    public function unsubscribe(Request $request, NewsletterSubscriber $subscriber): Response
    {
        $source = $request->input('List-Unsubscribe') === 'One-Click' ? 'one_click' : 'link';

        $subscriber = $this->consent->unsubscribe($subscriber, $source, $request->ip(), $request->userAgent());

        return $this->unsubscribePage($request, $subscriber, true);
    }

    public static function ensureEnabled(): void
    {
        abort_unless((bool) settings('newsletter.enabled', false), 404);
    }

    private function confirmPage(Request $request, string $status): Response
    {
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');

        return Inertia::render('Newsletter/Confirm', [
            'status' => $status,
            'action' => $request->url(),
        ]);
    }

    private function unsubscribePage(Request $request, NewsletterSubscriber $subscriber, bool $done): Response
    {
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');

        return Inertia::render('Newsletter/Unsubscribe', [
            'done' => $done,
            'email' => $subscriber->email,
            'action' => $request->fullUrl(),
        ]);
    }
}
