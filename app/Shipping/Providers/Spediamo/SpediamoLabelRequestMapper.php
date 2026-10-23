<?php

namespace App\Shipping\Providers\Spediamo;

use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Support\CarrierCodeMap;

/**
 * Maps a provider-neutral ShipmentRequest to the Spediamo POST /v1/shipments parameters
 * (docs/spediamo/shipments.md). Only this folder knows the carrier's field names.
 */
class SpediamoLabelRequestMapper
{
    public function __construct(
        private readonly array $defaultParcel = [],
        private readonly ?CarrierCodeMap $carriers = null,
        private readonly array $config = [],
    ) {}

    /**
     * @return array<string, scalar>
     */
    public function map(ShipmentRequest $request): array
    {
        $sender = $request->sender;
        $recipient = $request->recipient;

        $senderCountry = strtoupper(trim((string) $sender->country));
        $senderCountry = $senderCountry !== '' ? $senderCountry : 'IT';

        $recipientCountry = strtoupper(trim((string) $recipient->country));
        $recipientCountry = $recipientCountry !== '' ? $recipientCountry : 'IT';

        $type = ($senderCountry === 'IT' && $recipientCountry === 'IT') ? 'DOMESTIC' : 'INTERNATIONAL';

        $senderPostal = trim((string) $sender->postalCode);
        if ($senderCountry === 'IT' && is_numeric($senderPostal)) {
            $senderPostal = str_pad($senderPostal, 5, '0', STR_PAD_LEFT);
        }

        $recipientPostal = trim((string) $recipient->postalCode);
        if ($recipientCountry === 'IT' && is_numeric($recipientPostal)) {
            $recipientPostal = str_pad($recipientPostal, 5, '0', STR_PAD_LEFT);
        }

        $params = [
            'type' => $type,
            'senderName' => $sender->company !== '' ? $sender->company : $sender->name,
            'senderContactName' => $sender->name,
            'senderCountry' => $senderCountry,
            'senderProvince' => $sender->province,
            'senderTown' => $sender->city,
            'senderPostalCode' => $senderPostal,
            'senderAddress' => $sender->street,
            'senderPhone' => $sender->phone,
            'senderEmail' => $sender->email,
            'recipientName' => $recipient->company !== '' ? $recipient->company : $recipient->name,
            'recipientContactName' => $recipient->name,
            'recipientCountry' => $recipientCountry,
            'recipientProvince' => $recipient->province,
            'recipientTown' => $recipient->city,
            'recipientPostalCode' => $recipientPostal,
            'recipientAddress' => $recipient->street,
            'recipientPhone' => $recipient->phone,
            'recipientEmail' => $recipient->email,
            'referenceNumber' => $request->reference,
            // one "packages" line per parcel: "weight x length x width x height" (cm/kg)
            'packages' => $this->packages($request),
        ];

        if ($request->carrierCode !== '') {
            $courier = $this->carriers?->toProvider($request->carrierCode) ?? strtoupper($request->carrierCode);
            if ($courier !== '') {
                $params['courier'] = $courier;
            }
        }

        if ($request->notes !== '') {
            $params['deliveryNotes'] = $request->notes;
        }

        if ($request->insuranceAmount !== null && $request->insuranceAmount > 0) {
            $params['insurance'] = 'true';
            $params['insuranceAmount'] = number_format($request->insuranceAmount, 2, '.', '');
        }

        if ($request->codAmount !== null && $request->codAmount > 0) {
            $params['cashOnDelivery'] = 'true';
            $params['cashOnDeliveryAmount'] = number_format($request->codAmount, 2, '.', '');

            $iban = trim((string) ($this->config['iban'] ?? (function_exists('settings') ? settings('store.company_iban', '') : '')));
            $holder = trim((string) ($this->config['bank_account_holder'] ?? (function_exists('settings') ? settings('store.account_holder', '') : '')));

            if ($iban !== '') {
                $params['iban'] = $iban;
            }

            if ($holder !== '') {
                $params['bankAccountHolder'] = $holder;
            }
        }

        return array_filter($params, fn ($value) => $value !== '' && $value !== null);
    }

    private function packages(ShipmentRequest $request): string
    {
        $lines = [];

        foreach ($request->parcels as $parcel) {
            $lines[] = implode('x', [
                $this->num($parcel->weightKg),
                $this->num($parcel->lengthCm ?? 0),
                $this->num($parcel->widthCm ?? 0),
                $this->num($parcel->heightCm ?? 0),
            ]);
        }

        if (empty($lines) && ! empty($this->defaultParcel)) {
            $weight = (float) ($this->defaultParcel['weight'] ?? $this->defaultParcel['weightKg'] ?? $this->defaultParcel['weight_kg'] ?? 1);
            $length = (float) ($this->defaultParcel['length'] ?? $this->defaultParcel['lengthCm'] ?? $this->defaultParcel['length_cm'] ?? 0);
            $width = (float) ($this->defaultParcel['width'] ?? $this->defaultParcel['widthCm'] ?? $this->defaultParcel['width_cm'] ?? 0);
            $height = (float) ($this->defaultParcel['height'] ?? $this->defaultParcel['heightCm'] ?? $this->defaultParcel['height_cm'] ?? 0);

            $lines[] = implode('x', [
                $this->num($weight),
                $this->num($length),
                $this->num($width),
                $this->num($height),
            ]);
        }

        return implode(',', $lines);
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
