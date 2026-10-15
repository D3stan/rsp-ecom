<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingUserVerification;
use App\Models\User;
use App\Notifications\PendingUserEmailVerification;
use App\Services\EmailService;
use App\Services\Newsletter\MarketingConsent;
use App\Support\RegistrationAutoLogin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class.'|unique:'.PendingUserVerification::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'newsletter' => ['sometimes', 'boolean'],
        ]);

        // Create pending verification record instead of user
        $pendingUser = PendingUserVerification::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'verification_token' => PendingUserVerification::generateToken(),
            'token_expires_at' => now()->addHours(24),
        ]);

        // This browser is the one the "verification pending" page may log in once the email is verified
        RegistrationAutoLogin::remember($request, $pendingUser->email);

        // Send verification email
        Notification::route('mail', $pendingUser->email)
            ->notify(new PendingUserEmailVerification($pendingUser));

        // Unticked by default; the double opt-in mail goes out next to the account verification mail
        if ($request->boolean('newsletter') && settings('newsletter.enabled', false)) {
            app(MarketingConsent::class)->subscribe($pendingUser->email, 'register', app()->getLocale(), null, $request->ip(), $request->userAgent());
        }

        return redirect()->route('verification.pending')->with('email', $pendingUser->email);
    }
}
