<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Validation of a customer address, shared by the checkout form and the address book.
 * Destinations are a setting; an Italian state must be a province code (ItalianProvinces); the phone is checked
 * loosely (digits, spaces and punctuation, 6-15 digits: E.164 allows 15) rather than against one country's format.
 */
class AddressRules
{
    /**
     * @param  string  $prefix  key prefix of the address in the input, e.g. 'shipping_address.'
     */
    public static function for(string $prefix = ''): array
    {
        return [
            $prefix.'first_name' => 'required|string|max:255',
            $prefix.'last_name' => 'required|string|max:255',
            $prefix.'company' => 'nullable|string|max:255',
            $prefix.'address_line_1' => 'required|string|max:255',
            $prefix.'address_line_2' => 'nullable|string|max:255',
            $prefix.'city' => 'required|string|max:255',
            $prefix.'state' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($prefix) {
                if (request()->input($prefix.'country') === 'IT' && ! array_key_exists((string) $value, ItalianProvinces::ALL)) {
                    $fail(__('checkout.province_invalid'));
                }
            }],
            $prefix.'postal_code' => 'required|string|max:20',
            $prefix.'country' => ['required', 'string', 'size:2', Rule::in((array) settings('shipping.destinations', ['IT']))],
            $prefix.'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                $digits = preg_replace('/\D/', '', (string) $value);

                if (! preg_match('/^\+?[\d\s().\/-]+$/', (string) $value) || strlen($digits) < 6 || strlen($digits) > 15) {
                    $fail(__('checkout.phone_invalid'));
                }
            }],
        ];
    }
}
