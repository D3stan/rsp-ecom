<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Models\PendingUserVerification;
use App\Models\User;
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
    public function show(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('email');
        
        if (!$email) {
            return redirect()->route('register');
        }

        $pendingUser = PendingUserVerification::where('email', $email)->first();

        if (!$pendingUser) {
            if (User::where('email', $email)->exists()) {
                return redirect()->route('login')->with('status', 'Your email is already verified. Please log in.');
            }
            return redirect()->route('register');
        }

        // Keep email available in flash data for subsequent page refreshes
        $request->session()->flash('email', $email);

        return Inertia::render('auth/verification-pending', [
            'email' => $email,
            'status' => $request->session()->get('status'),
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
            \Illuminate\Support\Facades\Log::warning('Pending verification link rejected: invalid signature', [
                'request_url' => $request->fullUrl(),
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'port' => $request->getPort(),
                'app_url' => config('app.url'),
                'expires' => $request->query('expires'),
                'now' => now()->timestamp,
                'x_forwarded_proto' => $request->header('X-Forwarded-Proto'),
                'x_forwarded_host' => $request->header('X-Forwarded-Host'),
                'x_forwarded_port' => $request->header('X-Forwarded-Port'),
            ]);

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

        // A newsletter sign-up made with the registration form belongs to this account from now on
        NewsletterSubscriber::query()
            ->where('email', NewsletterSubscriber::normalizeEmail($user->email))
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        // Fire the registered event for any listeners
        event(new Registered($user));

        // Send welcome email
        $emailService = app(EmailService::class);
        $emailService->sendWelcomeEmail($user);

        // Log the user in
        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('success', 'Email verified successfully! Welcome to ' . settings('store.name') . '!');
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

        return back()
            ->with('status', 'verification-link-sent')
            ->with('email', $pendingUser->email);
    }
}
