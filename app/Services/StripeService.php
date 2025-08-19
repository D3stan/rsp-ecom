<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeService
{
    /**
     * Create a Stripe client instance with proper configuration
     */
    public static function getClient(): ?StripeClient
    {
        $stripeSecret = env('STRIPE_SECRET') ?: Setting::get('stripe_secret_key', '');
        
        if (empty($stripeSecret)) {
            Log::error('Stripe secret key not configured');
            return null;
        }
        
        return new StripeClient($stripeSecret);
    }

    /**
     * Get Stripe secret key from environment or database settings
     */
    public static function getSecretKey(): ?string
    {
        $stripeSecret = env('STRIPE_SECRET') ?: Setting::get('stripe_secret_key', '');
        
        return !empty($stripeSecret) ? $stripeSecret : null;
    }

    /**
     * Get Stripe public key from environment or database settings
     */
    public static function getPublicKey(): ?string
    {
        $stripePublic = env('STRIPE_KEY') ?: Setting::get('stripe_public_key', '');
        
        return !empty($stripePublic) ? $stripePublic : null;
    }

    /**
     * Check if Stripe is properly configured
     */
    public static function isConfigured(): bool
    {
        return !empty(self::getSecretKey()) && !empty(self::getPublicKey());
    }
}
