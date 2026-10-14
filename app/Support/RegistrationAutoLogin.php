<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Lets the browser that registered an email be logged in once that email is verified, possibly from another
 * device (the "verification pending" page polls VerificationStatusController). Proof of being that browser:
 * a random nonce kept in its session, matched against a hash stored server-side for the email. One use only,
 * and only shortly after the verification.
 */
class RegistrationAutoLogin
{
    private const SESSION_KEY = 'registration_auto_login';

    /** Longer than the 24 h verification link, so a link clicked at the last minute still works. */
    private const TTL_HOURS = 25;

    /** A verification older than this no longer logs anyone in: the browser is sent to the login page. */
    public const WINDOW_MINUTES = 30;

    /**
     * Called at registration: the current browser becomes the one allowed to be logged in for this email.
     */
    public static function remember(Request $request, string $email): void
    {
        $nonce = Str::random(40);

        $request->session()->put(self::SESSION_KEY, ['email' => $email, 'nonce' => $nonce]);
        Cache::put(self::cacheKey($email), hash('sha256', $nonce), now()->addHours(self::TTL_HOURS));
    }

    /**
     * Is this request coming from the browser that registered $email (and was not consumed yet)?
     */
    public static function isRegistrant(Request $request, string $email): bool
    {
        if (! $request->hasSession() || $email === '') {
            return false;
        }

        $entry = $request->session()->get(self::SESSION_KEY);
        $stored = Cache::get(self::cacheKey($email));

        return is_array($entry)
            && is_string($entry['email'] ?? null)
            && is_string($entry['nonce'] ?? null)
            && is_string($stored)
            && strcasecmp($entry['email'], $email) === 0
            && hash_equals($stored, hash('sha256', $entry['nonce']));
    }

    /**
     * Uses up the registrant proof for the verified $user: logs them in when the verification is recent.
     * Callers must check isRegistrant() first. Returns whether the user is now logged in.
     */
    public static function consume(Request $request, User $user): bool
    {
        $request->session()->forget(self::SESSION_KEY);
        Cache::forget(self::cacheKey($user->email));

        if (! $user->hasVerifiedEmail() || $user->email_verified_at->lt(now()->subMinutes(self::WINDOW_MINUTES))) {
            return false;
        }

        Auth::login($user);
        $request->session()->regenerate();

        return true;
    }

    private static function cacheKey(string $email): string
    {
        return 'registration-auto-login:'.hash('sha256', Str::lower($email));
    }
}
