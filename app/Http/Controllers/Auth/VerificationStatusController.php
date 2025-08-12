<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingUserVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificationStatusController extends Controller
{
    /**
     * Check if a pending verification has been completed
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $email = $request->query('email');
        
        if (!$email) {
            return response()->json(['verified' => false, 'error' => 'Email parameter required'], 400);
        }

        // Check if user exists and is verified
        $user = User::where('email', $email)->first();
        if ($user && $user->hasVerifiedEmail()) {
            // If user is verified but not logged in to this session, log them in
            if (!Auth::check() || Auth::id() !== $user->id) {
                Auth::login($user);
            }
            
            return response()->json([
                'verified' => true,
                'user_id' => $user->id,
                'authenticated' => true,
                'redirect_url' => route('dashboard')
            ]);
        }

        // Check if pending verification still exists
        $pendingVerification = PendingUserVerification::where('email', $email)->first();
        if (!$pendingVerification) {
            // No user and no pending verification - something went wrong or user was manually verified
            if ($user) {
                // If user is verified but not logged in to this session, log them in
                if (!Auth::check() || Auth::id() !== $user->id) {
                    Auth::login($user);
                }
                
                return response()->json([
                    'verified' => true,
                    'user_id' => $user->id,
                    'authenticated' => true,
                    'redirect_url' => route('dashboard')
                ]);
            }
            
            return response()->json([
                'verified' => false,
                'error' => 'No pending verification found',
                'redirect_url' => route('register')
            ]);
        }

        // Check if verification has expired
        if ($pendingVerification->isExpired()) {
            return response()->json([
                'verified' => false,
                'expired' => true,
                'message' => 'Verification link has expired. Please request a new one.'
            ]);
        }

        // Still pending
        return response()->json([
            'verified' => false,
            'pending' => true,
            'expires_at' => $pendingVerification->token_expires_at->toISOString()
        ]);
    }
}
