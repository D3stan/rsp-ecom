<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Support\ShippingIssue;

final class SpediamoIssueMapper
{
    /** Spediamo request field => our recipient field (the order's address form uses the same names). */
    private const RECIPIENT = [
        'recipientPostalCode' => 'recipient.postal_code',
        'recipientTown' => 'recipient.city',
        'recipientProvince' => 'recipient.province',
        'recipientAddress' => 'recipient.street',
        'recipientPhone' => 'recipient.phone',
        'recipientEmail' => 'recipient.email',
        'recipientName' => 'recipient.name',
        'recipientContactName' => 'recipient.name',
        'recipientCountry' => 'recipient.country',
    ];

    /**
     * @param  array<int|string, mixed>  $errors  the `errors` list of a Spediamo response
     * @return list<ShippingIssue>
     */
    public static function fromErrors(array $errors): array
    {
        $issues = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue; // scalar errors have no field: the exception message is all there is
            }

            $raw = (string) ($error['field'] ?? '');
            $field = self::RECIPIENT[$raw] ?? null;
            $kind = match (true) {
                $field !== null => ShippingIssue::ADDRESS,
                str_starts_with($raw, 'sender') => ShippingIssue::SETTINGS,
                str_starts_with($raw, 'packages') => ShippingIssue::PARCEL,
                default => ShippingIssue::OTHER,
            };

            $issues[] = new ShippingIssue(
                SpediamoRateProvider::NAME,
                $kind,
                $field ?? ($raw !== '' ? $raw : null),
                (string) ($error['code'] ?? ''),
                (string) ($error['message'] ?? ''),
            );
        }

        return $issues;
    }
}
