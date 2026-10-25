<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingUserVerification;
use App\Models\User;
use App\Support\RegistrationAutoLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationStatusController extends Controller
{
    public function checkStatus(Request $request): JsonResponse
    {
        $email = $request->query('email');

        if (! $email) {
            return response()->json(['verified' => false, 'error' => 'Email parameter required'], 400);
        }

        if (! RegistrationAutoLogin::isRegistrant($request, (string) $email)) {
            return response()->json(['verified' => false, 'pending' => true]);
        }

        $user = User::where('email', $email)->first();

        if ($user && $user->hasVerifiedEmail()) {
            $authenticated = RegistrationAutoLogin::consume($request, $user);

            if (! $authenticated) {
                $request->session()->flash('status', __('auth.verified_log_in'));
            }

            return response()->json([
                'verified' => true,
                'authenticated' => $authenticated,
                'redirect_url' => $authenticated ? route('dashboard') : route('login'),
            ]);
        }

        $pendingVerification = PendingUserVerification::where('email', $email)->first();

        if (! $pendingVerification) {
            return response()->json([
                'verified' => false,
                'error' => 'No pending verification found',
                'redirect_url' => route('register'),
            ]);
        }

        if ($pendingVerification->isExpired()) {
            return response()->json([
                'verified' => false,
                'expired' => true,
                'message' => 'Verification link has expired. Please request a new one.',
            ]);
        }

        return response()->json([
            'verified' => false,
            'pending' => true,
            'expires_at' => $pendingVerification->token_expires_at->toISOString(),
        ]);
    }
}
