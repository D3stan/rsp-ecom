<?php

namespace App\Listeners;

use App\Http\Middleware\CaptureGuestSessionId;
use App\Models\User;
use App\Services\CartMerger;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Throwable;

/**
 * Every sign-in path (password login, login after email verification, Google) fires Login: move the guest cart of
 * this browser into the user's cart. A failure is reported but never blocks the sign-in.
 */
class MergeGuestCartOnLogin
{
    public function __construct(private CartMerger $merger) {}

    public function handle(Login $event): void
    {
        $guestSessionId = app(Request::class)->attributes->get(CaptureGuestSessionId::ATTRIBUTE);

        if (! is_string($guestSessionId) || $guestSessionId === '' || ! $event->user instanceof User) {
            return;
        }

        try {
            $this->merger->merge($guestSessionId, $event->user);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
