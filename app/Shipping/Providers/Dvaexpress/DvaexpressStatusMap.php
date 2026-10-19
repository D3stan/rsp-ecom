<?php

namespace App\Shipping\Providers\Dvaexpress;

use App\Shipping\Tracking\Enums\TrackingStatus;
use Illuminate\Support\Facades\Log;

final class DvaexpressStatusMap
{
    /** @var array<string, TrackingStatus> */
    private const DEFAULTS = [
        // Delivered
        '000' => TrackingStatus::Delivered,
        'DELIVERED' => TrackingStatus::Delivered,
        'CONSEGNATA' => TrackingStatus::Delivered,
        'CONSEGNATO' => TrackingStatus::Delivered,

        // Out for delivery
        'OUT' => TrackingStatus::OutForDelivery,
        'IN CONSEGNA' => TrackingStatus::OutForDelivery,
        'MESSA IN CONSEGNA' => TrackingStatus::OutForDelivery,
        'OUT_FOR_DELIVERY' => TrackingStatus::OutForDelivery,

        // In transit
        'RIT' => TrackingStatus::InTransit,
        'RITIRATA' => TrackingStatus::InTransit,
        'PPP' => TrackingStatus::InTransit,
        'PARTITA' => TrackingStatus::InTransit,
        'THE' => TrackingStatus::InTransit,
        'IN TRANSITO' => TrackingStatus::InTransit,
        'TRANSITO' => TrackingStatus::InTransit,
        'ARR' => TrackingStatus::InTransit,
        'HUB' => TrackingStatus::InTransit,
        'PARTENZA' => TrackingStatus::InTransit,
        'ARRIVO' => TrackingStatus::InTransit,

        // Info received
        'INFO' => TrackingStatus::InfoReceived,
        'CREATA' => TrackingStatus::InfoReceived,
        'PRENOTATA' => TrackingStatus::InfoReceived,
        'ORDINE CREATO' => TrackingStatus::InfoReceived,

        // Exception
        'GIAC' => TrackingStatus::Exception,
        'GIACENZA' => TrackingStatus::Exception,
        'ASSENTE' => TrackingStatus::Exception,
        'DESTINATARIO ASSENTE' => TrackingStatus::Exception,
        'INDIRIZZO ERRATO' => TrackingStatus::Exception,
        'DANNEGGIATA' => TrackingStatus::Exception,
        'SMARRITA' => TrackingStatus::Exception,
        'ECCEZIONE' => TrackingStatus::Exception,
        'RIFIUTATA' => TrackingStatus::Exception,

        // Returned
        'RESO' => TrackingStatus::Returned,
        'RITORNO AL MITTENTE' => TrackingStatus::Returned,
        'RESTITUITA' => TrackingStatus::Returned,
    ];

    /** @var array<string, TrackingStatus> */
    private array $map;

    /**
     * @param  array<array-key, mixed>  $overrides
     */
    public function __construct(array $overrides = [])
    {
        $this->map = self::DEFAULTS;

        foreach ($overrides as $code => $status) {
            $key = self::normalize((string) $code);
            $resolved = $status instanceof TrackingStatus ? $status : TrackingStatus::tryFrom(strtolower(trim((string) $status)));

            if ($key !== '' && $resolved !== null) {
                $this->map[$key] = $resolved;
            }
        }
    }

    public function map(?string $status, ?string $descrizione = null, ?string $firma = null): TrackingStatus
    {
        if ($firma !== null && trim($firma) !== '') {
            return TrackingStatus::Delivered;
        }

        $statusCode = self::normalize($status);
        $desc = self::normalize($descrizione);

        if ($statusCode !== '' && isset($this->map[$statusCode])) {
            return $this->map[$statusCode];
        }

        if ($desc !== '' && isset($this->map[$desc])) {
            return $this->map[$desc];
        }

        if ($desc !== '') {
            foreach ($this->map as $pattern => $mappedStatus) {
                if (str_contains($desc, $pattern)) {
                    return $mappedStatus;
                }
            }
        }

        if ($statusCode !== '' || $desc !== '') {
            $raw = $status ?? $descrizione ?? '';
            Log::info('Dvaexpress tracking returned unmapped status', ['raw_status' => $raw]);
        }

        return TrackingStatus::Unknown;
    }

    public function isMapped(?string $status, ?string $descrizione = null, ?string $firma = null): bool
    {
        if ($firma !== null && trim($firma) !== '') {
            return true;
        }

        $statusCode = self::normalize($status);
        $desc = self::normalize($descrizione);

        if ($statusCode === '' && $desc === '') {
            return true;
        }

        if (isset($this->map[$statusCode]) || isset($this->map[$desc])) {
            return true;
        }

        if ($desc !== '') {
            foreach ($this->map as $pattern => $mappedStatus) {
                if (str_contains($desc, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, TrackingStatus>
     */
    public function all(): array
    {
        return $this->map;
    }

    public static function normalize(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
