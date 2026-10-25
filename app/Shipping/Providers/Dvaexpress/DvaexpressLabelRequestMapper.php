<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Support\CarrierCodeMap;

class DvaexpressLabelRequestMapper
{
    private readonly CarrierCodeMap $carriers;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
        ?CarrierCodeMap $carriers = null,
    ) {
        $this->carriers = $carriers ?? CarrierCodeMap::fromDriverConfig($this->config);
    }

    /**
     * Map ShipmentRequest to order-fast payload.
     *
     * @return array<string, mixed>
     */
    public function mapOrderFast(ShipmentRequest $request): array
    {
        $tipoSpedizione = $this->resolveShipmentType($request->sender->country, $request->recipient->country);
        $cosaSpedire = (string) ($this->config['default_cosa_spedire'] ?? 'M');
        $vettore = $this->carriers->toProvider($request->carrierCode) ?? (string) ($this->config['default_vettore'] ?? strtoupper($request->carrierCode));

        $custom = substr($request->reference, 0, 20);
        $noteCliente = $request->notes !== '' ? $request->notes : $request->reference;
        $noteContenuto = (string) ($this->config['default_content'] ?? 'Merce varia');

        $accessori = [];
        if ($request->insuranceAmount !== null && $request->insuranceAmount > 0) {
            $accessori['assicurazione'] = 'S';
            $accessori['assicurazione_importo'] = round($request->insuranceAmount, 2);
        }
        if ($request->codAmount !== null && $request->codAmount > 0) {
            $accessori['contrassegno'] = 'S';
            $accessori['contrassegno_importo'] = round($request->codAmount, 2);
        }

        $dettagli = [
            'tipo_spedizione' => $tipoSpedizione,
            'cosa_spedire' => $cosaSpedire,
            'vettore' => $vettore,
            'file_ldv' => true,
            'custom' => $custom,
            'note_cliente' => $noteCliente,
            'note_contenuto' => $noteContenuto,
        ];

        if (! empty($accessori)) {
            $dettagli['accessori'] = $accessori;
        }

        return [
            'call' => 'order-fast',
            'dettagli' => $dettagli,
            'colli' => $this->mapColli($request->parcels),
            'mittente' => $this->mapMittente($request->sender),
            'destinatario' => $this->mapDestinatario($request->recipient),
        ];
    }

    /**
     * Map ShipmentRequest and quoteToken to order payload.
     *
     * @return array<string, mixed>
     */
    public function mapOrderFromQuote(ShipmentRequest $request, string $quoteToken): array
    {
        $custom = substr($request->reference, 0, 20);
        $noteCliente = $request->notes !== '' ? $request->notes : $request->reference;

        $accessori = [];
        if ($request->insuranceAmount !== null && $request->insuranceAmount > 0) {
            $accessori['assicurazione'] = 'S';
        }
        if ($request->codAmount !== null && $request->codAmount > 0) {
            $accessori['contrassegno'] = 'S';
        }

        $dettagli = [
            'codice_offerta' => $quoteToken,
            'custom' => $custom,
            'note_cliente' => $noteCliente,
        ];

        if (! empty($accessori)) {
            $dettagli['accessori'] = $accessori;
        }

        return [
            'call' => 'order',
            'dettagli' => $dettagli,
            'mittente' => $this->mapMittente($request->sender),
            'destinatario' => $this->mapDestinatario($request->recipient),
            'ritiro' => $this->mapRitiro($request->pickupDate),
        ];
    }

    public function resolveShipmentType(string $senderCountry, string $recipientCountry): string
    {
        $sender = strtoupper(trim($senderCountry));
        $recipient = strtoupper(trim($recipientCountry));

        if ($sender === 'IT' && $recipient === 'IT') {
            return 'N';
        }

        if ($sender === 'IT' && $recipient !== 'IT') {
            return 'E';
        }

        if ($sender !== 'IT' && $recipient === 'IT') {
            return 'I';
        }

        return 'E';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMittente(Address $sender): array
    {
        $phone = trim($sender->phone);
        $phoneFallback = $phone !== '' ? $phone : '0000000000';
        $name = trim($sender->name);
        $company = trim($sender->company);
        $nominativo = $company !== '' ? $company : ($name !== '' ? $name : 'Mittente');
        $contatto = $name !== '' ? $name : $nominativo;

        $country = strtoupper(trim($sender->country));
        $province = trim($sender->province);
        if ($country !== 'IT' && $province === '') {
            $province = '--';
        }

        $postalCode = trim((string) $sender->postalCode);
        if (($country === 'IT' || $country === '') && is_numeric($postalCode)) {
            $postalCode = str_pad($postalCode, 5, '0', STR_PAD_LEFT);
        }

        $taxCode = (string) ($this->config['sender_tax_code'] ?? '');
        if ($taxCode === '' && function_exists('settings')) {
            $taxCode = (string) settings('store.vat_number', '00000000000');
        }
        if ($taxCode === '') {
            $taxCode = '00000000000';
        }

        return [
            'nominativo' => $nominativo,
            'indirizzo' => $sender->street,
            'cap' => $postalCode,
            'localita' => $sender->city,
            'provincia' => $province,
            'nazione' => $country !== '' ? $country : 'IT',
            'email' => $sender->email,
            'telefono' => $phoneFallback,
            'cellulare' => $phoneFallback,
            'contatto' => $contatto,
            'codicefiscale' => $taxCode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDestinatario(Address $recipient): array
    {
        $phone = trim($recipient->phone);
        $phoneFallback = $phone !== '' ? $phone : '0000000000';
        $name = trim($recipient->name);
        $company = trim($recipient->company);
        $nominativo = $company !== '' ? $company : ($name !== '' ? $name : 'Destinatario');
        $contatto = $name !== '' ? $name : $nominativo;

        $country = strtoupper(trim($recipient->country));
        $province = trim($recipient->province);
        if ($country !== 'IT' && $province === '') {
            $province = '--';
        }

        $postalCode = trim((string) $recipient->postalCode);
        if (($country === 'IT' || $country === '') && is_numeric($postalCode)) {
            $postalCode = str_pad($postalCode, 5, '0', STR_PAD_LEFT);
        }

        $data = [
            'nominativo' => $nominativo,
            'indirizzo' => $recipient->street,
            'cap' => $postalCode,
            'localita' => $recipient->city,
            'provincia' => $province,
            'nazione' => $country !== '' ? $country : 'IT',
            'email' => $recipient->email,
            'telefono' => $phoneFallback,
            'cellulare' => $phoneFallback,
            'contatto' => $contatto,
            'codicefiscale' => '',
        ];

        if (in_array($country, ['US', 'CA'], true)) {
            $data['country'] = $province;
        }

        return $data;
    }

    /**
     * @param  list<object>  $parcels
     * @return list<array<string, mixed>>
     */
    private function mapColli(array $parcels): array
    {
        $defaultWeight = (float) ($this->config['default_parcel']['weight_kg'] ?? 1.0);
        $defaultLength = (int) ($this->config['default_parcel']['length_cm'] ?? 30);
        $defaultWidth = (int) ($this->config['default_parcel']['width_cm'] ?? 20);
        $defaultHeight = (int) ($this->config['default_parcel']['height_cm'] ?? 15);

        if (empty($parcels)) {
            return [
                [
                    'peso' => $defaultWeight,
                    'larghezza' => $defaultLength,
                    'profondita' => $defaultWidth,
                    'altezza' => $defaultHeight,
                    'nr_colli' => 1,
                ],
            ];
        }

        $colli = [];
        foreach ($parcels as $parcel) {
            $weight = property_exists($parcel, 'weightKg') ? (float) $parcel->weightKg : $defaultWeight;
            $length = property_exists($parcel, 'lengthCm') && $parcel->lengthCm !== null ? (int) round($parcel->lengthCm) : $defaultLength;
            $width = property_exists($parcel, 'widthCm') && $parcel->widthCm !== null ? (int) round($parcel->widthCm) : $defaultWidth;
            $height = property_exists($parcel, 'heightCm') && $parcel->heightCm !== null ? (int) round($parcel->heightCm) : $defaultHeight;

            $colli[] = [
                'peso' => $weight > 0 ? round($weight, 3) : $defaultWeight,
                'larghezza' => $width > 0 ? $width : $defaultLength,
                'profondita' => $length > 0 ? $length : $defaultWidth,
                'altezza' => $height > 0 ? $height : $defaultHeight,
                'nr_colli' => 1,
            ];
        }

        return $colli;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRitiro(?string $pickupDate): array
    {
        $pickup = (array) ($this->config['pickup'] ?? []);

        return [
            'prenotazione' => (string) ($pickup['enabled'] ?? 'S'),
            'dettagli' => [
                'ritirodove' => (string) ($pickup['location'] ?? 'M'),
                'disponibile_dal' => (string) ($pickupDate ?? $pickup['date'] ?? now()->addWeekday()->format('Y-m-d')),
                'disponibile_ora' => (string) ($pickup['time'] ?? '09:00'),
                'ritiro_mattina_dalle' => (string) ($pickup['morning_from'] ?? '09:00'),
                'ritiro_mattina_alle' => (string) ($pickup['morning_to'] ?? '13:00'),
                'ritiro_pomeriggio_dalle' => (string) ($pickup['afternoon_from'] ?? '14:00'),
                'ritiro_pomeriggio_alle' => (string) ($pickup['afternoon_to'] ?? '18:00'),
            ],
        ];
    }
}
