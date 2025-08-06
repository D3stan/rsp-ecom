<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General Settings
            ['key' => 'site_name', 'value' => 'RSP Ecommerce', 'type' => 'string'],
            ['key' => 'site_description', 'value' => 'A modern ecommerce platform built with Laravel and React', 'type' => 'string'],
            ['key' => 'contact_email', 'value' => 'info@rspecommerce.com', 'type' => 'string'],
            ['key' => 'contact_phone', 'value' => '+39 123 456 7890', 'type' => 'string'],
            ['key' => 'company_address', 'value' => 'Via Roma 123, 00100 Roma RM, Italy', 'type' => 'string'],
            ['key' => 'default_currency', 'value' => 'EUR', 'type' => 'string'],
            ['key' => 'timezone', 'value' => 'Europe/Rome', 'type' => 'string'],

            // Payment Settings
            ['key' => 'stripe_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'stripe_public_key', 'value' => '', 'type' => 'string'],
            ['key' => 'stripe_secret_key', 'value' => '', 'type' => 'string'],
            ['key' => 'stripe_webhook_secret', 'value' => '', 'type' => 'string'],
            ['key' => 'paypal_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'paypal_client_id', 'value' => '', 'type' => 'string'],
            ['key' => 'paypal_client_secret', 'value' => '', 'type' => 'string'],
            ['key' => 'minimum_order_amount', 'value' => '0', 'type' => 'string'],

            // Shipping Settings
            ['key' => 'shipping_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'free_shipping_threshold', 'value' => '50', 'type' => 'string'],
            ['key' => 'default_shipping_cost', 'value' => '5', 'type' => 'string'],
            ['key' => 'shipping_calculation_method', 'value' => 'flat_rate', 'type' => 'string'],
            ['key' => 'allow_international_shipping', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'processing_time_days', 'value' => '2', 'type' => 'string'],
            ['key' => 'shipping_zones', 'value' => '[]', 'type' => 'json'],

            // Tax Settings
            ['key' => 'tax_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'default_tax_rate', 'value' => '22', 'type' => 'string'],
            ['key' => 'prices_include_tax', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'tax_calculation_method', 'value' => 'destination', 'type' => 'string'],
            ['key' => 'collect_tax_for_digital_products', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'tax_number', 'value' => '', 'type' => 'string'],

            // Email Settings
            ['key' => 'email_notifications_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'order_confirmation_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'order_shipped_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'order_delivered_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'order_cancelled_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'newsletter_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'from_email', 'value' => 'noreply@rspecommerce.com', 'type' => 'string'],
            ['key' => 'from_name', 'value' => 'RSP Ecommerce', 'type' => 'string'],
            ['key' => 'admin_notification_email', 'value' => 'admin@rspecommerce.com', 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }
    }
}
