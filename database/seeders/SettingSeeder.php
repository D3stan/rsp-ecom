<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'site_name',
                'value' => 'RSP Ecommerce',
                'type' => 'string',
            ],
            [
                'key' => 'site_description',
                'value' => 'Your trusted online shopping destination',
                'type' => 'string',
            ],
            [
                'key' => 'default_currency',
                'value' => 'USD',
                'type' => 'string',
            ],
            [
                'key' => 'tax_rate',
                'value' => '22',
                'type' => 'string',
            ],
            [
                'key' => 'prices_include_tax',
                'value' => '1',
                'type' => 'boolean',
            ],
            [
                'key' => 'shipping_enabled',
                'value' => '1',
                'type' => 'boolean',
            ],
            [
                'key' => 'free_shipping_threshold',
                'value' => '100.00',
                'type' => 'string',
            ],
            [
                'key' => 'stripe_settings',
                'value' => json_encode([
                    'public_key' => 'pk_test_...',
                    'secret_key' => 'sk_test_...',
                    'webhook_secret' => 'whsec_...',
                ]),
                'type' => 'json',
            ],
            [
                'key' => 'email_settings',
                'value' => json_encode([
                    'from_name' => 'RSP Ecommerce',
                    'from_email' => 'noreply@rspecommerce.com',
                    'support_email' => 'support@rspecommerce.com',
                ]),
                'type' => 'json',
            ],
            [
                'key' => 'social_media',
                'value' => json_encode([
                    'facebook' => 'https://facebook.com/rspecommerce',
                    'twitter' => 'https://twitter.com/rspecommerce',
                    'instagram' => 'https://instagram.com/rspecommerce',
                ]),
                'type' => 'json',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
            ],
            [
                'key' => 'products_per_page',
                'value' => '12',
                'type' => 'string',
            ],
            [
                'key' => 'max_cart_items',
                'value' => '100',
                'type' => 'string',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']], // Find by key
                $setting // Update or create with all data
            );
        }
    }
}
