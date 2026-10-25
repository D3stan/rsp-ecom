<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingUserVerification;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        try {
            return Socialite::driver('google')
                ->scopes(['openid', 'profile', 'email'])
                ->redirect();
        } catch (\Exception $e) {
            \Log::error('Google OAuth redirect error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return redirect()->route('login')->with('error', 'Unable to connect to Google. Please try again later.');
        }
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            if (config('app.env') === 'local') {
                $googleUser = Socialite::driver('google')
                    ->setHttpClient(new Client(['verify' => false]))
                    ->user();
            } else {
                $googleUser = Socialite::driver('google')->user();
            }

            $currentUser = Auth::user();
            if ($currentUser) {
                if ($currentUser->google_id === $googleUser->getId()) {
                    return redirect()->route('profile.edit')->with('info', 'This Google account is already linked to your account.');
                }

                $otherUserWithGoogleId = User::where('google_id', $googleUser->getId())
                    ->where('id', '!=', $currentUser->id)
                    ->first();

                if ($otherUserWithGoogleId) {
                    return redirect()->route('profile.edit')->with('error', 'This Google account is already linked to another user account.');
                }

                $currentUser->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);

                return redirect()->route('profile.edit')->with('success', 'Google account linked successfully!');
            }

            $user = User::where('google_id', $googleUser->getId())->first();

            if ($user) {
                Auth::login($user);

                return redirect()->intended('/dashboard');
            }

            $existingUser = User::where('email', $googleUser->getEmail())->first();

            if ($existingUser && ! $existingUser->is_active) {
                return redirect()->route('login')->withErrors(['email' => __('auth.failed')]);
            }

            if ($existingUser) {
                $existingUser->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);

                Auth::login($existingUser);

                return redirect()->intended('/dashboard');
            }

            $pendingUser = PendingUserVerification::where('email', $googleUser->getEmail())->first();

            if ($pendingUser) {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'role' => 'customer',
                    'is_active' => true,
                    'password' => $pendingUser->password,
                ]);

                $pendingUser->delete();

                Auth::login($user);

                return redirect()->intended('/dashboard')->with('success', 'Account verified via Google! Welcome to '.settings('store.name').'!');
            }

            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'role' => 'customer',
                'is_active' => true,
                'password' => null,
            ]);

            Auth::login($user);

            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            \Log::error('Google OAuth callback error', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            if (str_contains($e->getMessage(), 'disallowed_useragent')) {
                return redirect()->route('login')->with('error', 'Google authentication failed. Please ensure you are using a supported browser and try again.');
            }

            if (str_contains($e->getMessage(), '403')) {
                return redirect()->route('login')->with('error', 'Google authentication access denied. Please try again or contact support.');
            }

            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }
    }

    public function unlinkGoogle(): RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->password) {
            return redirect()->back()->with('error', 'Please set a password before unlinking your Google account.');
        }

        $user->update([
            'google_id' => null,
            'avatar' => null,
        ]);

        return redirect()->back()->with('success', 'Google account unlinked successfully.');
    }
}
