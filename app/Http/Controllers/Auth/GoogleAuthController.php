<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingUserVerification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        try {
            // Use default browser user-agent for Google OAuth compliance
            // Do not use custom HTTP client to avoid "embedded user-agent" policy violations
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

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            // For local development only, disable SSL verification to avoid certificate issues
            // Do not set custom user-agent to comply with Google's "secure browsers" policy
            if (config('app.env') === 'local') {
                $googleUser = Socialite::driver('google')
                    ->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                    ->user();
            } else {
                $googleUser = Socialite::driver('google')->user();
            }
            
            // Check if user already exists with this Google ID
            $user = User::where('google_id', $googleUser->getId())->first();
            
            if ($user) {
                // User exists, log them in
                Auth::login($user);
                return redirect()->intended('/dashboard');
            }
            
            // Check if there's already a logged-in user wanting to link their account
            $currentUser = Auth::user();
            if ($currentUser) {
                // Check if this Google account is already linked to another user
                $googleAccountAlreadyLinked = User::where('google_id', $googleUser->getId())->exists();
                
                if ($googleAccountAlreadyLinked) {
                    return redirect()->route('profile.edit')->with('error', 'This Google account is already linked to another user account.');
                }
                
                // Link Google account to the current logged-in user
                $currentUser->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(), // Ensure Google accounts are verified
                ]);
                
                return redirect()->route('profile.edit')->with('success', 'Google account linked successfully!');
            }
            
            // Check if user exists with the same email
            $existingUser = User::where('email', $googleUser->getEmail())->first();
            
            if ($existingUser) {
                // Link Google account to existing user
                $existingUser->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(), // Ensure Google accounts are verified
                ]);
                
                Auth::login($existingUser);
                return redirect()->intended('/dashboard');
            }

            // Check if there's a pending verification for this email
            $pendingUser = PendingUserVerification::where('email', $googleUser->getEmail())->first();
            
            if ($pendingUser) {
                // Create verified user from pending verification
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(), // Google accounts are pre-verified
                    'role' => 'customer',
                    'is_active' => true,
                    'password' => $pendingUser->password, // Keep the password they set during registration
                ]);

                // Delete the pending verification
                $pendingUser->delete();

                Auth::login($user);
                return redirect()->intended('/dashboard')->with('success', 'Account verified via Google! Welcome to ' . config('app.name') . '!');
            }
            
            // Create new user
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(), // Google accounts are pre-verified
                'role' => 'customer',
                'is_active' => true,
                'password' => null, // No password for Google users initially
            ]);
            
            Auth::login($user);
            return redirect()->intended('/dashboard');
            
        } catch (\Exception $e) {
            \Log::error('Google OAuth callback error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Handle specific Google OAuth errors
            if (str_contains($e->getMessage(), 'disallowed_useragent')) {
                return redirect()->route('login')->with('error', 'Google authentication failed. Please ensure you are using a supported browser and try again.');
            }
            
            if (str_contains($e->getMessage(), '403')) {
                return redirect()->route('login')->with('error', 'Google authentication access denied. Please try again or contact support.');
            }
            
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }
    }
    
    /**
     * Unlink Google account from user.
     */
    public function unlinkGoogle(): RedirectResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Ensure user has a password set before unlinking Google
        if (!$user->password) {
            return redirect()->back()->with('error', 'Please set a password before unlinking your Google account.');
        }
        
        $user->update([
            'google_id' => null,
            'avatar' => null,
        ]);
        
        return redirect()->back()->with('success', 'Google account unlinked successfully.');
    }
}
