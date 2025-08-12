<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            // For local development, disable SSL verification to avoid certificate issues
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
            
            // Check if user exists with the same email
            $existingUser = User::where('email', $googleUser->getEmail())->first();
            
            if ($existingUser) {
                // Link Google account to existing user
                $existingUser->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
                
                Auth::login($existingUser);
                return redirect()->intended('/dashboard');
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
            ]);
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
