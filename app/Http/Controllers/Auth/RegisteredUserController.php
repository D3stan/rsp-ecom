<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingUserVerification;
use App\Models\User;
use App\Notifications\PendingUserEmailVerification;
use App\Services\Newsletter\MarketingConsent;
use App\Support\RegistrationAutoLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class.'|unique:'.PendingUserVerification::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'newsletter' => ['sometimes', 'boolean'],
        ]);

        $pendingUser = PendingUserVerification::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'verification_token' => PendingUserVerification::generateToken(),
            'token_expires_at' => now()->addHours(24),
        ]);

        RegistrationAutoLogin::remember($request, $pendingUser->email);

        Notification::route('mail', $pendingUser->email)
            ->notify(new PendingUserEmailVerification($pendingUser));

        if ($request->boolean('newsletter') && settings('newsletter.enabled', false)) {
            app(MarketingConsent::class)->subscribe($pendingUser->email, 'register', app()->getLocale(), null, $request->ip(), $request->userAgent());
        }

        return redirect()->route('verification.pending')->with('email', $pendingUser->email);
    }
}
