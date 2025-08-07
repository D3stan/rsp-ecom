<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = $this->getAllSettings();
        
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function updateGeneral(Request $request)
    {
                $request->validate([
            'site_name' => 'required|string|max:255',
            'site_description' => 'nullable|string|max:1000',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'company_address' => 'nullable|string|max:1000',
            'default_currency' => ['required', Rule::in(['EUR', 'USD', 'GBP'])],
            'timezone' => 'required|string|max:100',
            'company_iban' => 'nullable|string|max:50',
            'company_account_holder' => 'nullable|string|max:255',
        ]);

        foreach ($request->only([
            'site_name',
            'site_description', 
            'contact_email',
            'contact_phone',
            'company_address',
            'default_currency',
            'timezone',
            'company_iban',
            'company_account_holder'
        ]) as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'General settings updated successfully.');
    }

    public function updatePayment(Request $request)
    {
        $request->validate([
            'stripe_enabled' => 'boolean',
            'stripe_public_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:255',
            'stripe_webhook_secret' => 'nullable|string|max:255',
            'paypal_enabled' => 'boolean',
            'paypal_client_id' => 'nullable|string|max:255',
            'paypal_client_secret' => 'nullable|string|max:255',
            'minimum_order_amount' => 'nullable|numeric|min:0',
        ]);

        foreach ($request->only([
            'stripe_enabled',
            'stripe_public_key',
            'stripe_secret_key', 
            'stripe_webhook_secret',
            'paypal_enabled',
            'paypal_client_id',
            'paypal_client_secret',
            'minimum_order_amount'
        ]) as $key => $value) {
            $type = is_bool($value) ? 'boolean' : 'string';
            Setting::set($key, $value, $type);
        }

        return back()->with('success', 'Payment settings updated successfully.');
    }

    public function updateShipping(Request $request)
    {
        $request->validate([
            'shipping_enabled' => 'boolean',
            'free_shipping_threshold' => 'nullable|numeric|min:0',
            'default_shipping_cost' => 'nullable|numeric|min:0',
            'shipping_calculation_method' => ['required', Rule::in(['flat_rate', 'weight_based', 'zone_based'])],
            'allow_international_shipping' => 'boolean',
            'processing_time_days' => 'nullable|integer|min:1|max:30',
            'shipping_zones' => 'nullable|array',
        ]);

        foreach ($request->only([
            'shipping_enabled',
            'free_shipping_threshold',
            'default_shipping_cost',
            'shipping_calculation_method',
            'allow_international_shipping', 
            'processing_time_days',
            'shipping_zones'
        ]) as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_array($value) => 'json',
                default => 'string'
            };
            Setting::set($key, $value, $type);
        }

        return back()->with('success', 'Shipping settings updated successfully.');
    }

    public function updateTax(Request $request)
    {
        $request->validate([
            'tax_enabled' => 'boolean',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'prices_include_tax' => 'boolean',
            'tax_calculation_method' => ['required', Rule::in(['destination', 'origin'])],
            'collect_tax_for_digital_products' => 'boolean',
            'tax_number' => 'nullable|string|max:100',
        ]);

        foreach ($request->only([
            'tax_enabled',
            'default_tax_rate',
            'prices_include_tax',
            'tax_calculation_method',
            'collect_tax_for_digital_products',
            'tax_number'
        ]) as $key => $value) {
            $type = is_bool($value) ? 'boolean' : 'string';
            Setting::set($key, $value, $type);
        }

        return back()->with('success', 'Tax settings updated successfully.');
    }

    public function updateEmail(Request $request)
    {
        $request->validate([
            'email_notifications_enabled' => 'boolean',
            'order_confirmation_enabled' => 'boolean',
            'order_shipped_enabled' => 'boolean',
            'order_delivered_enabled' => 'boolean',
            'order_cancelled_enabled' => 'boolean',
            'newsletter_enabled' => 'boolean',
            'from_email' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'admin_notification_email' => 'nullable|email|max:255',
        ]);

        foreach ($request->only([
            'email_notifications_enabled',
            'order_confirmation_enabled',
            'order_shipped_enabled',
            'order_delivered_enabled',
            'order_cancelled_enabled',
            'newsletter_enabled',
            'from_email',
            'from_name',
            'admin_notification_email'
        ]) as $key => $value) {
            $type = is_bool($value) ? 'boolean' : 'string';
            Setting::set($key, $value, $type);
        }

        return back()->with('success', 'Email settings updated successfully.');
    }

    private function getAllSettings(): array
    {
        return [
            // General Settings
            'site_name' => Setting::get('site_name', 'RSP Ecommerce'),
            'site_description' => Setting::get('site_description', ''),
            'contact_email' => Setting::get('contact_email', 'info@example.com'),
            'contact_phone' => Setting::get('contact_phone', ''),
            'company_address' => Setting::get('company_address', ''),
            'default_currency' => Setting::get('default_currency', 'EUR'),
            'timezone' => Setting::get('timezone', 'Europe/Rome'),
            'company_iban' => Setting::get('company_iban', ''),
            'company_account_holder' => Setting::get('company_account_holder', ''),

            // Payment Settings  
            'stripe_enabled' => Setting::get('stripe_enabled', true),
            'stripe_public_key' => Setting::get('stripe_public_key', ''),
            'stripe_secret_key' => Setting::get('stripe_secret_key', ''),
            'stripe_webhook_secret' => Setting::get('stripe_webhook_secret', ''),
            'paypal_enabled' => Setting::get('paypal_enabled', false),
            'paypal_client_id' => Setting::get('paypal_client_id', ''),
            'paypal_client_secret' => Setting::get('paypal_client_secret', ''),
            'minimum_order_amount' => Setting::get('minimum_order_amount', 0),

            // Shipping Settings
            'shipping_enabled' => Setting::get('shipping_enabled', true),
            'free_shipping_threshold' => Setting::get('free_shipping_threshold', 50),
            'default_shipping_cost' => Setting::get('default_shipping_cost', 5),
            'shipping_calculation_method' => Setting::get('shipping_calculation_method', 'flat_rate'),
            'allow_international_shipping' => Setting::get('allow_international_shipping', true),
            'processing_time_days' => Setting::get('processing_time_days', 2),
            'shipping_zones' => Setting::get('shipping_zones', []),

            // Tax Settings
            'tax_enabled' => Setting::get('tax_enabled', true),
            'default_tax_rate' => Setting::get('default_tax_rate', 22),
            'prices_include_tax' => Setting::get('prices_include_tax', true),
            'tax_calculation_method' => Setting::get('tax_calculation_method', 'destination'),
            'collect_tax_for_digital_products' => Setting::get('collect_tax_for_digital_products', true),
            'tax_number' => Setting::get('tax_number', ''),

            // Email Settings
            'email_notifications_enabled' => Setting::get('email_notifications_enabled', true),
            'order_confirmation_enabled' => Setting::get('order_confirmation_enabled', true),
            'order_shipped_enabled' => Setting::get('order_shipped_enabled', true),
            'order_delivered_enabled' => Setting::get('order_delivered_enabled', true),
            'order_cancelled_enabled' => Setting::get('order_cancelled_enabled', true),
            'newsletter_enabled' => Setting::get('newsletter_enabled', false),
            'from_email' => Setting::get('from_email', 'noreply@example.com'),
            'from_name' => Setting::get('from_name', 'RSP Ecommerce'),
            'admin_notification_email' => Setting::get('admin_notification_email', ''),
        ];
    }
}
