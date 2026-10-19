<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Labels\Contracts\CancelsShipments;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Support\CarrierCodeMap;

class DvaexpressLabelProvider implements CancelsShipments, LabelProvider
{
    public const NAME = 'dvaexpress';

    private readonly DvaexpressClient $client;

    private readonly CarrierCodeMap $carriers;

    private readonly DvaexpressLabelRequestMapper $mapper;

    /** @var array<string, string> in-memory PDF cache keyed by externalId */
    private array $inlineLabels = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
        ?DvaexpressClient $client = null,
        ?DvaexpressLabelRequestMapper $mapper = null,
    ) {
        $this->client = $client ?? new DvaexpressClient($this->config);
        $this->carriers = CarrierCodeMap::fromDriverConfig($this->config);
        $this->mapper = $mapper ?? new DvaexpressLabelRequestMapper($this->config, $this->carriers);
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

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        $quoteToken = property_exists($request, 'quoteToken') && is_string($request->quoteToken)
            ? trim($request->quoteToken)
            : '';

        if ($quoteToken !== '') {
            $payload = $this->mapper->mapOrderFromQuote($request, $quoteToken);
            $orderRes = $this->client->postLabel('order', $payload);

            $orderId = (string) ($orderRes['id_ordine'] ?? '');
            if ($orderId === '') {
                throw new LabelProviderUnavailable('Dvaexpress order creation returned no order ID.');
            }

            $waybillRes = $this->client->postLabel('getwaybill', [
                'call' => 'getwaybill',
                'details' => [
                    'order_id' => is_numeric($orderId) ? (int) $orderId : $orderId,
                    'waybill_base64' => 'Y',
                ],
            ]);

            $trackingNumber = '';
            if (is_array($waybillRes['waybill_number'] ?? null)) {
                $trackingNumber = trim((string) ($waybillRes['waybill_number'][0] ?? ''));
            } elseif (is_scalar($waybillRes['waybill_number'] ?? null)) {
                $trackingNumber = trim((string) $waybillRes['waybill_number']);
            }
            if ($trackingNumber === '' && isset($waybillRes['single_waybills'][0])) {
                $swTracking = $waybillRes['single_waybills'][0]['waybill_number'] ?? $waybillRes['single_waybills'][0]['ldv'] ?? '';
                $trackingNumber = is_array($swTracking) ? trim((string) ($swTracking[0] ?? '')) : trim((string) $swTracking);
            }

            if ($trackingNumber === '') {
                throw new LabelRejected('Dvaexpress: shipment order created but no tracking number assigned yet.');
            }

            $b64 = '';
            if (is_array($waybillRes['waybill_base64'] ?? null)) {
                $b64 = (string) ($waybillRes['waybill_base64'][0] ?? '');
            } elseif (is_scalar($waybillRes['waybill_base64'] ?? null)) {
                $b64 = (string) $waybillRes['waybill_base64'];
            }
            if ($b64 !== '') {
                $pdfBytes = base64_decode($b64);
                if (str_starts_with($pdfBytes, '%PDF')) {
                    $this->inlineLabels[$orderId] = $pdfBytes;
                }
            }

            $raw = array_merge($orderRes, $waybillRes);
            unset($raw['waybill_base64']);
            if (isset($raw['single_waybills']) && is_array($raw['single_waybills'])) {
                foreach ($raw['single_waybills'] as &$sw) {
                    if (is_array($sw)) {
                        unset($sw['waybill_base64'], $sw['ldv_base64']);
                    }
                }
            }
            $raw['driver'] = self::NAME;

            return new ShipmentResult(
                externalId: $orderId,
                trackingNumber: $trackingNumber,
                carrierCode: $request->carrierCode,
                raw: $raw,
            );
        }

        // Order-fast flow
        $payload = $this->mapper->mapOrderFast($request);
        $response = $this->client->postLabel('order-fast', $payload);

        $orderId = (string) ($response['id_ordine'] ?? '');
        if ($orderId === '') {
            throw new LabelProviderUnavailable('Dvaexpress order-fast returned no order ID.');
        }

        $trackingNumber = '';
        if (is_array($response['lettera_vettura'] ?? null)) {
            $trackingNumber = trim((string) ($response['lettera_vettura'][0] ?? ''));
        } elseif (is_scalar($response['lettera_vettura'] ?? null)) {
            $trackingNumber = trim((string) $response['lettera_vettura']);
        }
        if ($trackingNumber === '' && isset($response['single_waybills'][0])) {
            $swLdv = $response['single_waybills'][0]['ldv'] ?? $response['single_waybills'][0]['lettera_vettura'] ?? '';
            $trackingNumber = is_array($swLdv) ? trim((string) ($swLdv[0] ?? '')) : trim((string) $swLdv);
        }

        if ($trackingNumber === '') {
            throw new LabelRejected('Dvaexpress: fast order created but no tracking number was returned.');
        }

        $b64 = '';
        if (is_array($response['ldv_base64'] ?? null)) {
            $b64 = (string) ($response['ldv_base64'][0] ?? '');
        } elseif (is_scalar($response['ldv_base64'] ?? null)) {
            $b64 = (string) $response['ldv_base64'];
        }
        if ($b64 !== '') {
            $pdfBytes = base64_decode($b64);
            if (str_starts_with($pdfBytes, '%PDF')) {
                $this->inlineLabels[$orderId] = $pdfBytes;
            }
        }

        $raw = $response;
        unset($raw['ldv_base64']);
        if (isset($raw['single_waybills']) && is_array($raw['single_waybills'])) {
            foreach ($raw['single_waybills'] as &$sw) {
                if (is_array($sw)) {
                    unset($sw['ldv_base64'], $sw['waybill_base64']);
                }
            }
        }
        $raw['driver'] = self::NAME;

        return new ShipmentResult(
            externalId: $orderId,
            trackingNumber: $trackingNumber,
            carrierCode: $request->carrierCode,
            raw: $raw,
        );
    }

    public function label(ShipmentResult|string $externalId): LabelDocument
    {
        $id = $externalId instanceof ShipmentResult ? $externalId->externalId : trim($externalId);

        if (isset($this->inlineLabels[$id])) {
            return new LabelDocument(
                contents: $this->inlineLabels[$id],
                filename: "label-{$id}.pdf",
                mimeType: 'application/pdf',
            );
        }

        if ($externalId instanceof ShipmentResult && ! empty($externalId->raw['ldv_base64'])) {
            $bytes = base64_decode((string) $externalId->raw['ldv_base64']);
            if (str_starts_with($bytes, '%PDF')) {
                $this->inlineLabels[$id] = $bytes;

                return new LabelDocument(
                    contents: $bytes,
                    filename: "label-{$id}.pdf",
                    mimeType: 'application/pdf',
                );
            }
        }

        $response = $this->client->postLabel('getwaybill', [
            'call' => 'getwaybill',
            'details' => [
                'order_id' => is_numeric($id) ? (int) $id : $id,
                'waybill_base64' => 'Y',
            ],
        ]);

        $b64 = '';
        if (is_array($response['waybill_base64'] ?? null)) {
            $b64 = (string) ($response['waybill_base64'][0] ?? '');
        } elseif (is_scalar($response['waybill_base64'] ?? null)) {
            $b64 = (string) $response['waybill_base64'];
        }
        $pdfBytes = base64_decode($b64);

        if (! str_starts_with($pdfBytes, '%PDF')) {
            throw new LabelProviderUnavailable("Dvaexpress: waybill for order {$id} is not a valid PDF.");
        }

        $this->inlineLabels[$id] = $pdfBytes;

        return new LabelDocument(
            contents: $pdfBytes,
            filename: "label-{$id}.pdf",
            mimeType: 'application/pdf',
        );
    }

    public function cancelShipment(string $externalId): void
    {
        $orderId = trim($externalId);
        $payload = [
            'ordini' => [is_numeric($orderId) ? (int) $orderId : $orderId],
            'solo_verifica' => false,
        ];

        $response = $this->client->postLabel('richiesta-storno', $payload);

        $esiti = (array) ($response['esiti'] ?? []);
        $esito = $esiti[0] ?? null;

        if (! is_array($esito)) {
            throw new LabelProviderUnavailable('Dvaexpress cancellation returned no order outcome.');
        }

        $codice = strtolower((string) ($esito['codice'] ?? ''));

        if (($esito['ammesso'] ?? false) === true || in_array($codice, ['ammesso', 'gia_richiesto', 'cancellato'], true)) {
            return;
        }

        if (in_array($codice, ['tracking_non_verificabile', 'non_aggiornato'], true)) {
            throw new LabelProviderUnavailable((string) ($esito['messaggio'] ?? 'Cancellation check temporarily unavailable.'));
        }

        throw new LabelRejected((string) ($esito['messaggio'] ?? "Order {$externalId} cannot be cancelled ({$codice})."));
    }
}
