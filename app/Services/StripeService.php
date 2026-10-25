<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeService
{
    public static function getClient(): ?StripeClient
    {
        $stripeSecret = config('cashier.secret') ?: Setting::get('stripe_secret_key', '');

        if (empty($stripeSecret)) {
            Log::error('Stripe secret key not configured');

            return null;
        }

        return new StripeClient($stripeSecret);
    }

    public static function getSecretKey(): ?string
    {
        $stripeSecret = config('cashier.secret') ?: Setting::get('stripe_secret_key', '');

        return ! empty($stripeSecret) ? $stripeSecret : null;
    }

    public static function getPublicKey(): ?string
    {
        $stripePublic = config('cashier.key') ?: Setting::get('stripe_public_key', '');

        return ! empty($stripePublic) ? $stripePublic : null;
    }

    public static function isConfigured(): bool
    {
        return ! empty(self::getSecretKey()) && ! empty(self::getPublicKey());
    }
}
