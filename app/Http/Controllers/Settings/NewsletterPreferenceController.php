<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Newsletter\NewsletterController;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterPreferenceController extends Controller
{
    public function update(Request $request, MarketingConsent $consent): RedirectResponse
    {
        NewsletterController::ensureEnabled();

        $data = $request->validate(['subscribed' => ['required', 'boolean']]);
        $user = $request->user();

        if ($data['subscribed']) {
            $subscriber = $consent->subscribe($user->email, 'account', app()->getLocale(), $user, $request->ip(), $request->userAgent());

            return back()->with('success', $subscriber->isConfirmed() ? __('newsletter.account.subscribed') : __('newsletter.check_inbox'));
        }

        $subscriber = self::subscriberFor($user);

        if ($subscriber !== null) {
            $consent->unsubscribe($subscriber, 'account', $request->ip(), $request->userAgent());
        }

        return back()->with('success', __('newsletter.account.unsubscribed'));
    }

    /**
     * The `newsletter` prop of the profile page: null while the newsletter is off (the switch is hidden).
     *
     * @return array{status: string}|null
     */
    public static function pageProps(User $user): ?array
    {
        if (! settings('newsletter.enabled', false)) {
            return null;
        }

        return ['status' => self::subscriberFor($user)?->status ?? 'none'];
    }

    private static function subscriberFor(User $user): ?NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->where('email', NewsletterSubscriber::normalizeEmail($user->email))->first();
    }
}
