<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Billing Address
            'billing_address.first_name' => 'required|string|max:255',
            'billing_address.last_name' => 'required|string|max:255',
            'billing_address.email' => 'required|email|max:255',
            'billing_address.phone' => 'nullable|string|max:20',
            'billing_address.company' => 'nullable|string|max:255',
            'billing_address.address_line_1' => 'required|string|max:255',
            'billing_address.address_line_2' => 'nullable|string|max:255',
            'billing_address.city' => 'required|string|max:255',
            'billing_address.state' => 'required|string|max:255',
            'billing_address.postal_code' => 'required|string|max:20',
            'billing_address.country' => 'required|string|size:2', // ISO 3166-1 alpha-2

            // Shipping Address (optional if same as billing)
            'shipping_same_as_billing' => 'boolean',
            'shipping_address.first_name' => 'required_if:shipping_same_as_billing,false|string|max:255',
            'shipping_address.last_name' => 'required_if:shipping_same_as_billing,false|string|max:255',
            'shipping_address.company' => 'nullable|string|max:255',
            'shipping_address.address_line_1' => 'required_if:shipping_same_as_billing,false|string|max:255',
            'shipping_address.address_line_2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required_if:shipping_same_as_billing,false|string|max:255',
            'shipping_address.state' => 'required_if:shipping_same_as_billing,false|string|max:255',
            'shipping_address.postal_code' => 'required_if:shipping_same_as_billing,false|string|max:20',
            'shipping_address.country' => 'required_if:shipping_same_as_billing,false|string|size:2',

            // Payment & Options
            'payment_method_id' => 'nullable|string', // For saved payment methods
            'save_payment_method' => 'boolean',
            'promotion_code' => 'nullable|string|max:50',
            'allow_promotion_codes' => 'boolean',
            'collect_tax_id' => 'boolean',
            'tax_id_type' => 'nullable|string|in:eu_vat,us_ein,gb_vat,ca_bn,au_abn',
            'tax_id_value' => 'nullable|string|max:50',

            // Checkout Options
            'checkout_mode' => 'in:payment,subscription',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
            'locale' => 'nullable|string|in:en,es,fr,de,it',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'billing_address.first_name.required' => 'First name is required for billing address.',
            'billing_address.last_name.required' => 'Last name is required for billing address.',
            'billing_address.email.required' => 'Email is required for billing address.',
            'billing_address.email.email' => 'Please provide a valid email address.',
            'billing_address.address_line_1.required' => 'Street address is required for billing address.',
            'billing_address.city.required' => 'City is required for billing address.',
            'billing_address.state.required' => 'State/Province is required for billing address.',
            'billing_address.postal_code.required' => 'Postal code is required for billing address.',
            'billing_address.country.required' => 'Country is required for billing address.',
            'billing_address.country.size' => 'Country must be a valid 2-letter country code.',

            'shipping_address.first_name.required_if' => 'First name is required for shipping address.',
            'shipping_address.last_name.required_if' => 'Last name is required for shipping address.',
            'shipping_address.address_line_1.required_if' => 'Street address is required for shipping address.',
            'shipping_address.city.required_if' => 'City is required for shipping address.',
            'shipping_address.state.required_if' => 'State/Province is required for shipping address.',
            'shipping_address.postal_code.required_if' => 'Postal code is required for shipping address.',
            'shipping_address.country.required_if' => 'Country is required for shipping address.',
            'shipping_address.country.size' => 'Country must be a valid 2-letter country code.',

            'checkout_mode.in' => 'Invalid checkout mode selected.',
            'tax_id_type.in' => 'Invalid tax ID type selected.',
            'locale.in' => 'Unsupported locale selected.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'billing_address.first_name' => 'billing first name',
            'billing_address.last_name' => 'billing last name',
            'billing_address.email' => 'billing email',
            'billing_address.phone' => 'billing phone',
            'billing_address.company' => 'billing company',
            'billing_address.address_line_1' => 'billing street address',
            'billing_address.address_line_2' => 'billing address line 2',
            'billing_address.city' => 'billing city',
            'billing_address.state' => 'billing state/province',
            'billing_address.postal_code' => 'billing postal code',
            'billing_address.country' => 'billing country',

            'shipping_address.first_name' => 'shipping first name',
            'shipping_address.last_name' => 'shipping last name',
            'shipping_address.company' => 'shipping company',
            'shipping_address.address_line_1' => 'shipping street address',
            'shipping_address.address_line_2' => 'shipping address line 2',
            'shipping_address.city' => 'shipping city',
            'shipping_address.state' => 'shipping state/province',
            'shipping_address.postal_code' => 'shipping postal code',
            'shipping_address.country' => 'shipping country',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'shipping_same_as_billing' => $this->boolean('shipping_same_as_billing', true),
            'save_payment_method' => $this->boolean('save_payment_method', false),
            'allow_promotion_codes' => $this->boolean('allow_promotion_codes', true),
            'collect_tax_id' => $this->boolean('collect_tax_id', false),
            'checkout_mode' => $this->input('checkout_mode', 'payment'),
            'locale' => $this->input('locale', app()->getLocale()),
        ]);

        // Generate URLs if not provided
        if (!$this->has('success_url')) {
            $this->merge(['success_url' => route('checkout.success')]);
        }

        if (!$this->has('cancel_url')) {
            $this->merge(['cancel_url' => route('checkout.cancel')]);
        }
    }
}
