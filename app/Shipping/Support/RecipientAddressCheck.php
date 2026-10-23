<?php

namespace App\Shipping\Support;

use App\Shipping\Rates\DTO\RateAddress;

/**
 * Cheap checks on the order's recipient before (and next to) the carriers' own validation. Provider "store".
 */
final class RecipientAddressCheck
{
    public const PROVIDER = 'store';

    /** @return list<ShippingIssue> */
    public static function issues(RateAddress $recipient): array
    {
        $italy = strtoupper($recipient->country) === 'IT';
        $postal = trim($recipient->postalCode);
        $province = trim($recipient->province);

        $codes = [
            'recipient.postal_code' => $postal === '' ? 'required' : ($italy && ! preg_match('/^\d{5}$/', $postal) ? 'invalid' : null),
            'recipient.city' => trim($recipient->city) === '' ? 'required' : null,
            'recipient.street' => trim($recipient->street) === '' ? 'required' : null,
            'recipient.phone' => trim($recipient->phone) === '' ? 'required' : null,
            'recipient.province' => $italy && ! preg_match('/^[A-Za-z]{2}$/', $province) ? ($province === '' ? 'required' : 'invalid') : null,
        ];

        $issues = [];
        foreach (array_filter($codes) as $field => $code) {
            $issues[] = new ShippingIssue(self::PROVIDER, ShippingIssue::ADDRESS, $field, $code, $code === 'required' ? 'Required' : 'Not valid');
        }

        return $issues;
    }
}
