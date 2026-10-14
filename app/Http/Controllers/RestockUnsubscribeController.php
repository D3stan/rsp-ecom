<?php

namespace App\Http\Controllers;

use App\Models\User;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * One-click unsubscribe from back-in-stock emails, reached through the signed link in every such email (no login: the
 * signature is the proof). GET is the footer link and shows a confirmation page; POST is the RFC 8058 one-click request
 * mail clients send to the List-Unsubscribe URL (CSRF-exempt in bootstrap/app.php). Both switch the preference off.
 */
class RestockUnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $user): Response
    {
        if (! $request->hasValidSignature()) {
            return $request->isMethod('post')
                ? response('', 403)
                : $this->page($request, false)->setStatusCode(403);
        }

        User::query()->whereKey($user)->update(['notify_back_in_stock' => false]);

        return $request->isMethod('post') ? response('', 200) : $this->page($request, true);
    }

    private function page(Request $request, bool $unsubscribed): Response
    {
        // The URL carries a signature: keep it out of search engines, referrers and caches
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');

        return Inertia::render('Restock/Unsubscribed', ['unsubscribed' => $unsubscribed])
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }
}
