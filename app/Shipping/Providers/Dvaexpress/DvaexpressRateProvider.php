<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Rates\Contracts\InspectsBalance;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Rates\DTO\AccountBalance;
use App\Shipping\Rates\DTO\RateQuote;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Support\CarrierCodeMap;

class DvaexpressRateProvider implements InspectsBalance, RateProvider
{
    public const NAME = 'dvaexpress';

    private readonly DvaexpressClient $client;

    private readonly CarrierCodeMap $carriers;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
        ?DvaexpressClient $client = null,
    ) {
        $this->client = $client ?? new DvaexpressClient($this->config);
        $this->carriers = CarrierCodeMap::fromDriverConfig($this->config);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(string $carrierCode): bool
    {
        if (method_exists($this->carriers, 'supports')) {
            return $this->carriers->supports($carrierCode);
        }

        return $this->carriers->isEmpty() || $this->carriers->toProvider($carrierCode) !== null;
    }

    /**
     * Fetch rate quotes for shipment request.
     *
     * @return list<RateQuote>
     */
    public function quote(RateRequest $request): array
    {
        if (trim((string) $request->sender->postalCode) === '' || trim((string) $request->sender->city) === '') {
            throw new \App\Shipping\Rates\Exceptions\RateRejected(
                'Dvaexpress: Sender address requires at least postal code and city (configure shipping.sender_postal_code and shipping.sender_city in settings or .env).'
            );
        }

        if (trim((string) $request->recipient->postalCode) === '' || trim((string) $request->recipient->city) === '') {
            throw new \App\Shipping\Rates\Exceptions\RateRejected(
                'Dvaexpress: Recipient address requires at least postal code and city.'
            );
        }

        $senderCountry = strtoupper(trim($request->sender->country));
        $recipientCountry = strtoupper(trim($request->recipient->country));

        $senderPostal = trim((string) $request->sender->postalCode);
        if (($senderCountry === 'IT' || $senderCountry === '') && is_numeric($senderPostal)) {
            $senderPostal = str_pad($senderPostal, 5, '0', STR_PAD_LEFT);
        }

        $recipientPostal = trim((string) $request->recipient->postalCode);
        if (($recipientCountry === 'IT' || $recipientCountry === '') && is_numeric($recipientPostal)) {
            $recipientPostal = str_pad($recipientPostal, 5, '0', STR_PAD_LEFT);
        }

        if (in_array($recipientCountry, ['US', 'CA'], true) && trim((string) $request->recipient->province) === '') {
            throw new \App\Shipping\Rates\Exceptions\RateRejected(
                "Dvaexpress: Recipient province (state code) is required for {$recipientCountry}."
            );
        }

        $tipoSpedizione = $this->resolveShipmentType($senderCountry, $recipientCountry);

        $accessori = [];
        if ($request->codAmount !== null && $request->codAmount > 0) {
            $accessori['contrassegno_importo'] = round($request->codAmount, 2);
        }
        if ($request->insuranceAmount !== null && $request->insuranceAmount > 0) {
            $accessori['assicurazione_importo'] = round($request->insuranceAmount, 2);
        }

        $contentDesc = property_exists($request, 'contentDescription') && ! empty($request->contentDescription)
            ? (string) $request->contentDescription
            : (string) ($this->config['default_content'] ?? 'Merce varia');

        $dettagli = [
            'tipo_spedizione' => $tipoSpedizione,
            'cosa_spedire' => (string) ($this->config['default_cosa_spedire'] ?? 'M'),
            'contenuto' => $contentDesc,
        ];
        if (! empty($accessori)) {
            $dettagli['accessori'] = $accessori;
        }
        if (property_exists($request, 'coupon') && ! empty($request->coupon)) {
            $dettagli['coupon'] = (string) $request->coupon;
        }

        $colli = [];
        $defaultWeight = (float) ($this->config['default_parcel']['weight_kg'] ?? 1.0);
        $defaultLength = (int) ($this->config['default_parcel']['length_cm'] ?? 30);
        $defaultWidth = (int) ($this->config['default_parcel']['width_cm'] ?? 20);
        $defaultHeight = (int) ($this->config['default_parcel']['height_cm'] ?? 15);

        if (empty($request->parcels)) {
            $colli[] = [
                'peso' => $defaultWeight,
                'larghezza' => $defaultLength,
                'profondita' => $defaultWidth,
                'altezza' => $defaultHeight,
                'nr_colli' => 1,
            ];
        } else {
            foreach ($request->parcels as $parcel) {
                $qty = property_exists($parcel, 'quantity') ? max(1, (int) $parcel->quantity) : 1;
                $weight = property_exists($parcel, 'weightKg') ? (float) $parcel->weightKg : $defaultWeight;
                $length = property_exists($parcel, 'lengthCm') && $parcel->lengthCm !== null ? (int) round($parcel->lengthCm) : $defaultLength;
                $width = property_exists($parcel, 'widthCm') && $parcel->widthCm !== null ? (int) round($parcel->widthCm) : $defaultWidth;
                $height = property_exists($parcel, 'heightCm') && $parcel->heightCm !== null ? (int) round($parcel->heightCm) : $defaultHeight;

                $colli[] = [
                    'peso' => $weight > 0 ? round($weight * $qty, 3) : $defaultWeight * $qty,
                    'larghezza' => $width > 0 ? $width : $defaultLength,
                    'profondita' => $length > 0 ? $length : $defaultWidth,
                    'altezza' => $height > 0 ? $height : $defaultHeight,
                    'nr_colli' => $qty,
                ];
            }
        }

        $senderProv = trim($request->sender->province);
        if ($senderCountry !== 'IT' && $senderProv === '') {
            $senderProv = '--';
        }

        $recipProv = trim($request->recipient->province);
        if ($recipientCountry !== 'IT' && $recipProv === '') {
            $recipProv = '--';
        }

        $mittente = [
            'cap' => $senderPostal,
            'localita' => $request->sender->city,
            'provincia' => $senderProv,
            'nazione' => $senderCountry !== '' ? $senderCountry : 'IT',
        ];

        $destinatario = [
            'cap' => $recipientPostal,
            'localita' => $request->recipient->city,
            'provincia' => $recipProv,
            'nazione' => $recipientCountry !== '' ? $recipientCountry : 'IT',
        ];

        if (in_array($recipientCountry, ['US', 'CA'], true)) {
            $destinatario['country'] = $recipProv;
        }

        $payload = [
            'call' => 'quotation',
            'dettagli' => $dettagli,
            'colli' => $colli,
            'mittente' => $mittente,
            'destinatario' => $destinatario,
        ];

        $response = $this->client->postRate('quotation', $payload);

        $quotations = (array) ($response['quotation'] ?? []);
        $actualWeight = 0.0;
        foreach ($request->parcels as $p) {
            $w = property_exists($p, 'weightKg') ? (float) $p->weightKg : 0.0;
            $qty = property_exists($p, 'quantity') ? (int) $p->quantity : 1;
            $actualWeight += $w * max(1, $qty);
        }

        $quotes = [];
        foreach ($quotations as $item) {
            if (! is_array($item)) {
                continue;
            }

            $preferred = (property_exists($request, 'carrierCode') && $request->carrierCode !== null && trim($request->carrierCode) !== '')
                ? [trim($request->carrierCode)]
                : null;

            $vettore = (string) ($item['vettore'] ?? $item['famiglia'] ?? '');
            $famiglia = (string) ($item['famiglia'] ?? '');
            $carrierCode = $this->carriers->fromProvider($vettore, $preferred)
                ?? ($this->carriers->fromProvider($famiglia, $preferred)
                ?? strtolower($famiglia !== '' ? $famiglia : $vettore));

            $carrierName = (string) ($item['nome_vettore'] ?? ($famiglia !== '' ? $famiglia : $vettore));
            $serviceName = ((string) ($item['consegna'] ?? '')) === 'E' ? 'Express' : 'Standard';
            $netPrice = (float) ($item['totale_dovuto'] ?? 0.0);
            $totalPrice = (float) ($item['totale_dovuto'] ?? 0.0);
            $listPrice = isset($item['importo_tariffa']) && $item['importo_tariffa'] !== null ? (float) $item['importo_tariffa'] : null;
            $taxableWeightKg = isset($item['peso_volumetrico']) && $item['peso_volumetrico'] !== null ? (float) $item['peso_volumetrico'] : null;
            $dimApplied = $taxableWeightKg !== null && $taxableWeightKg > $actualWeight;
            $estimatedDelivery = isset($item['tempiconsegna']) && trim((string) $item['tempiconsegna']) !== '' ? (string) $item['tempiconsegna'] : null;
            $quoteToken = isset($item['codice_offerta']) && trim((string) $item['codice_offerta']) !== '' ? (string) $item['codice_offerta'] : null;

            $surcharges = [];
            if (! empty($item['flag_localitadisagiata'])) {
                $surcharges['remote_area'] = true;
            }
            if (! empty($item['flag_localitaperiferica'])) {
                $surcharges['peripheral_area'] = true;
            }
            if (isset($item['serviziopzionali']) && is_array($item['serviziopzionali'])) {
                $opts = $item['serviziopzionali'];
                if (($request->codAmount ?? 0) > 0 && isset($opts['contrassegno']['importo'])) {
                    $surcharges['cod'] = (float) $opts['contrassegno']['importo'];
                }
                if (($request->insuranceAmount ?? 0) > 0 && isset($opts['assicurazione']['importo'])) {
                    $surcharges['insurance'] = (float) $opts['assicurazione']['importo'];
                }
            }

            $warnings = [];
            if (! empty($item['messaggio_tariffa'])) {
                $warnings[] = (string) $item['messaggio_tariffa'];
            }
            if (! empty($item['flag_localitadisagiata'])) {
                $warnings[] = 'Spedizione verso località disagiata (supplemento applicato)';
            }

            $quotes[] = new RateQuote(
                provider: self::NAME,
                carrierCode: $carrierCode,
                carrierName: $carrierName,
                rawCarrierCode: $vettore,
                serviceName: $serviceName,
                netPrice: $netPrice,
                totalPrice: $totalPrice,
                listPrice: $listPrice,
                currency: 'EUR',
                taxableWeightKg: $taxableWeightKg,
                dimensionalWeightApplied: $dimApplied,
                estimatedDelivery: $estimatedDelivery,
                quoteToken: $quoteToken,
                surcharges: $surcharges,
                warnings: $warnings,
                raw: $item,
            );
        }

        if (property_exists($request, 'carrierCode') && $request->carrierCode !== null && trim($request->carrierCode) !== '') {
            $quotes = array_values(array_filter($quotes, fn (RateQuote $q) => $this->carriers->areSameCourier($q->carrierCode, $request->carrierCode)));
        }

        return $quotes;
    }

    /**
     * Inspect current prepaid balance.
     */
    public function balance(): AccountBalance
    {
        $res = $this->client->postRate('balance', ['call' => 'balance']);

        $balance = (float) ($res['credito_master'] ?? $res['balance_master'] ?? $res['balance'] ?? $res['credito'] ?? 0.0);

        return new AccountBalance(
            provider: self::NAME,
            balance: $balance,
            currency: 'EUR',
            raw: $res,
        );
    }

    private function resolveShipmentType(string $senderCountry, string $recipientCountry): string
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
}
