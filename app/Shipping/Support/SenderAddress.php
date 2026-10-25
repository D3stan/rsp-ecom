<?php

namespace App\Shipping\Support;

final class SenderAddress
{
    /**
     * @return array{name: string, street: string, city: string, postal_code: string, province: string, country: string, phone: string, email: string}
     */
    public static function fromSettings(): array
    {
        $field = fn (string $name, mixed $fallback = '') => trim((string) settings("shipping.sender_{$name}", ''))
            ?: trim((string) config("shipping.labels.sender.{$name}", ''))
            ?: trim((string) $fallback);

        $country = $field('country', settings('shipping.default_country', '')) ?: 'IT';
        $postalCode = $field('postal_code');
        if (strtoupper($country) === 'IT' && is_numeric($postalCode)) {
            $postalCode = str_pad($postalCode, 5, '0', STR_PAD_LEFT);
        }

        return [
            'name' => $field('name', settings('store.name', '')),
            'street' => $field('street', settings('store.address', '')),
            'city' => $field('city'),
            'postal_code' => $postalCode,
            'province' => $field('province'),
            'country' => $country,
            'phone' => $field('phone', settings('contact.phone', '')),
            'email' => $field('email', settings('contact.support_email', '')),
        ];
    }
}
