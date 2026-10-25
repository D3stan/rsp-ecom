<?php

namespace App\Shipping\Labels\Drivers;

use App\Shipping\Labels\Contracts\CancelsShipments;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;

class FakeLabelProvider implements CancelsShipments, LabelProvider
{
    public const NAME = 'fake';

    /** @var list<string> external ids cancelled through this instance (for assertions) */
    public array $cancelled = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(string $carrierCode): bool
    {
        return true;
    }

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        foreach ($request->parcels as $parcel) {
            if ($parcel->weightKg >= 100) {
                throw new LabelRejected('Parcel too heavy for this service (max 100 kg).');
            }

            if ($parcel->weightKg > 0 && $parcel->weightKg < 0.01) {
                throw new LabelProviderUnavailable('The label service is temporarily unavailable.');
            }
        }

        $seed = substr(sha1($request->reference.'|'.$request->recipient->postalCode), 0, 10);

        return new ShipmentResult(
            externalId: 'FAKE-'.$seed,
            trackingNumber: 'FKL'.strtoupper($seed),
            carrierCode: $request->carrierCode,
            raw: ['driver' => self::NAME, 'parcels' => count($request->parcels)],
        );
    }

    public function label(ShipmentResult|string $externalId): LabelDocument
    {
        $id = $externalId instanceof ShipmentResult ? $externalId->externalId : $externalId;

        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 100]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF label:".$id."\n";

        return new LabelDocument($pdf, 'label-'.$id.'.pdf');
    }

    public function cancelShipment(string $externalId): void
    {
        $this->cancelled[] = $externalId;
    }
}
