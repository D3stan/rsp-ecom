<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Guest users are allowed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Guest contact information
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:20'],
            
            // Billing address (required)
            'billing_address.first_name' => ['required', 'string', 'max:255'],
            'billing_address.last_name' => ['required', 'string', 'max:255'],
            'billing_address.address_line_1' => ['required', 'string', 'max:255'],
            'billing_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['required', 'string', 'max:255'],
            'billing_address.state' => ['required', 'string', 'max:255'],
            'billing_address.postal_code' => ['required', 'string', 'max:20'],
            'billing_address.country' => ['required', 'string', 'max:2'],
            
            // Shipping address (conditional)
            'shipping_same_as_billing' => ['boolean'],
            'shipping_address.first_name' => ['required_if:shipping_same_as_billing,false', 'nullable', 'string', 'max:255'],
            'shipping_address.last_name' => ['required_if:shipping_same_as_billing,false', 'nullable', 'string', 'max:255'],
            'shipping_address.address_line_1' => ['required_if:shipping_same_as_billing,false', 'nullable', 'string', 'max:255'],
            'shipping_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required_if:shipping_same_as_billing,false', 'nullable', 'string', 'max:255'],
            'shipping_address.state' => ['required_if:shipping_same_as_billing,false', 'nullable', 'string', 'max:255'],
            'shipping_address.postal_code' => ['required_if:shipping_same_as_billing,false', 'nullable', 'string', 'max:20'],
            'shipping_address.country' => ['required_if:shipping_same_as_billing,false', 'nullable', 'string', 'max:2'],
            
            // Payment options
            'payment_method_type' => ['required', 'string', 'in:card'],
            'save_payment_info' => ['boolean'],
            'promotion_code' => ['nullable', 'string', 'max:50'],
            
            // Tax ID collection (optional)
            'collect_tax_id' => ['boolean'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            
            // Cart session identifier for guest users
            'cart_session_id' => ['required', 'string'],
            
            // Terms and conditions
            'accept_terms' => ['required', 'accepted'],
            'marketing_consent' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'guest_email.required' => 'Email address is required for guest checkout.',
            'guest_email.email' => 'Please provide a valid email address.',
            'billing_address.*.required' => 'This billing address field is required.',
            'shipping_address.*.required_if' => 'This shipping address field is required when different from billing.',
            'cart_session_id.required' => 'Cart session is required for guest checkout.',
            'accept_terms.accepted' => 'You must accept the terms and conditions to proceed.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'guest_email' => 'email address',
            'guest_phone' => 'phone number',
            'billing_address.first_name' => 'billing first name',
            'billing_address.last_name' => 'billing last name',
            'billing_address.address_line_1' => 'billing address',
            'billing_address.city' => 'billing city',
            'billing_address.state' => 'billing state',
            'billing_address.postal_code' => 'billing postal code',
            'billing_address.country' => 'billing country',
            'shipping_address.first_name' => 'shipping first name',
            'shipping_address.last_name' => 'shipping last name',
            'shipping_address.address_line_1' => 'shipping address',
            'shipping_address.city' => 'shipping city',
            'shipping_address.state' => 'shipping state',
            'shipping_address.postal_code' => 'shipping postal code',
            'shipping_address.country' => 'shipping country',
        ];
    }
}
