<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingUserVerification;
use App\Notifications\PendingUserEmailVerification;
use App\Services\EmailService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class PendingUserVerificationController extends Controller
{
    /**
     * Show the pending verification notice.
     */
    public function show(Request $request): Response
    {
        $email = $request->session()->get('email');
        
        if (!$email) {
            return redirect()->route('register');
        }

        return Inertia::render('auth/verification-pending', [
            'email' => $email,
        ]);
    }

    /**
     * Verify the pending user's email and create the account.
     */
    public function verify(Request $request): RedirectResponse
    {
        $token = $request->route('token');
        $email = $request->route('email');

        if (!$request->hasValidSignature()) {
            return redirect()->route('register')->with('error', 'Invalid or expired verification link.');
        }

        $pendingUser = PendingUserVerification::where('email', $email)
            ->where('verification_token', $token)
            ->first();

        if (!$pendingUser) {
            return redirect()->route('register')->with('error', 'Invalid verification link.');
        }

        if ($pendingUser->isExpired()) {
            return redirect()->route('register')->with('error', 'Verification link has expired. Please register again.');
        }

        // Create the verified user
        $user = $pendingUser->createVerifiedUser();

        // Fire the registered event for any listeners
        event(new Registered($user));

        // Send welcome email
        $emailService = app(EmailService::class);
        $emailService->sendWelcomeEmail($user);

        // Log the user in
        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('success', 'Email verified successfully! Welcome to ' . config('app.name') . '!');
    }

    /**
     * Resend verification email.
     */
    public function resend(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|exists:pending_user_verifications,email',
        ]);

        $pendingUser = PendingUserVerification::where('email', $request->email)->first();

        if (!$pendingUser) {
            return back()->with('error', 'No pending verification found for this email.');
        }

        // Regenerate token if expired
        if ($pendingUser->isExpired()) {
            $pendingUser->regenerateToken();
        }

        // Send verification email
        Notification::route('mail', $pendingUser->email)
            ->notify(new PendingUserEmailVerification($pendingUser));

        return back()->with('status', 'verification-link-sent');
    }
}
