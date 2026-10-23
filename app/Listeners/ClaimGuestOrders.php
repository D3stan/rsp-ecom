<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Throwable;

/**
 * A customer who ordered as a guest and then signed up (or verified their email) gets those orders on the account.
 * Every sign-in path fires Login, so the claim happens on the first sign-in after the email is verified. A failure
 * is reported but never blocks the sign-in.
 */
class ClaimGuestOrders
{
    public function handleLogin(Login $event): void
    {
        $this->claim($event->user);
    }

    public function handleVerified(Verified $event): void
    {
        $this->claim($event->user);
    }

    private function claim(mixed $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        try {
            $user->claimGuestOrders();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
