<?php

namespace App\Http\Controllers;

use App\Models\User;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unsubscribe from back-in-stock emails through the signed link in every such email (no login: the signature is the
 * proof). Only POST changes anything, so link-prefetching mail scanners cannot unsubscribe anyone:
 *  - GET (the footer link) shows a page with an "Unsubscribe" button, which POSTs to the same signed URL;
 *  - POST from that page (Inertia) acts and shows the confirmation;
 *  - POST from a mail client (RFC 8058 one-click to the List-Unsubscribe URL) acts and answers 200.
 * The route is CSRF-exempt (bootstrap/app.php) because mail clients send no token; the signature protects it.
 */
class RestockUnsubscribeController extends Controller
{
    public const STATUS_CONFIRM = 'confirm';

    public const STATUS_DONE = 'done';

    public const STATUS_INVALID = 'invalid';

    public function __invoke(Request $request, string $user): Response
    {
        $fromMailClient = $request->isMethod('post') && ! $request->header('X-Inertia');

        if (! $request->hasValidSignature()) {
            return $fromMailClient ? response('', 403) : $this->page($request, self::STATUS_INVALID)->setStatusCode(403);
        }

        if ($request->isMethod('get')) {
            return $this->page($request, self::STATUS_CONFIRM);
        }

        User::query()->whereKey($user)->update(['notify_back_in_stock' => false]);

        return $fromMailClient ? response('', 200) : $this->page($request, self::STATUS_DONE);
    }

    private function page(Request $request, string $status): Response
    {
        // The URL carries a signature: keep it out of search engines, referrers and caches
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');

        return Inertia::render('Restock/Unsubscribed', [
            'status' => $status,
            // The signed URL the button posts to (the page itself was reached through it)
            'actionUrl' => $status === self::STATUS_CONFIRM ? $request->fullUrl() : null,
        ])
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }
}
