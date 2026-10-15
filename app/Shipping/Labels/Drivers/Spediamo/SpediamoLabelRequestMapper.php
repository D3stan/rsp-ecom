<?php

namespace App\Shipping\Labels\Drivers\Spediamo;

use App\Shipping\Labels\DTO\ShipmentRequest;

/**
 * Maps a provider-neutral ShipmentRequest to the Spediamo POST /v1/shipments parameters
 * (docs/spediamo/shipments.md). Only this folder knows the carrier's field names.
 */
class SpediamoLabelRequestMapper
{
    /**
     * @return array<string, scalar>
     */
    public function map(ShipmentRequest $request): array
    {
        $sender = $request->sender;
        $recipient = $request->recipient;

        $params = [
            'type' => 'packages',
            'senderName' => $sender->company !== '' ? $sender->company : $sender->name,
            'senderContactName' => $sender->name,
            'senderCountry' => $sender->country,
            'senderProvince' => $sender->province,
            'senderTown' => $sender->city,
            'senderPostalCode' => $sender->postalCode,
            'senderAddress' => $sender->street,
            'senderPhone' => $sender->phone,
            'senderEmail' => $sender->email,
            'recipientName' => $recipient->company !== '' ? $recipient->company : $recipient->name,
            'recipientContactName' => $recipient->name,
            'recipientCountry' => $recipient->country,
            'recipientProvince' => $recipient->province,
            'recipientTown' => $recipient->city,
            'recipientPostalCode' => $recipient->postalCode,
            'recipientAddress' => $recipient->street,
            'recipientPhone' => $recipient->phone,
            'recipientEmail' => $recipient->email,
            'referenceNumber' => $request->reference,
            // one "packages" line per parcel: "weight x length x width x height" (cm/kg)
            'packages' => $this->packages($request),
        ];

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

        return implode(',', $lines);
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
