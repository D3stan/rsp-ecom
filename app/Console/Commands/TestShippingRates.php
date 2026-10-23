<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateQuote;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\RateManager;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Throwable;

class TestShippingRates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'shipping:test-rates
                            {--driver= : Test specific driver (fake, or any registered rate driver)}
                            {--order= : Order ID or order number to load recipient and items from}
                            {--dest-cap=48015 : Recipient postal code}
                            {--dest-city=Cervia : Recipient city}
                            {--dest-prov=RA : Recipient province}
                            {--dest-country=IT : Recipient country}
                            {--dest-name=Test Customer : Recipient name}
                            {--dest-street=Via Roma 1 : Recipient street}
                            {--sender-cap= : Sender postal code override}
                            {--sender-city= : Sender city override}
                            {--sender-prov= : Sender province override}
                            {--sender-country= : Sender country override}
                            {--weight=1.0 : Parcel weight in kg}
                            {--length=35 : Parcel length in cm}
                            {--width=25 : Parcel width in cm}
                            {--height=8 : Parcel height in cm}
                            {--carrier= : Filter by carrier code (sda, brt, etc.)}
                            {--insurance= : Insurance amount in EUR}
                            {--cod= : Cash on delivery amount in EUR}
                            {--api-key= : Override API key for the driver during this test run}
                            {--token= : Override bearer token for the driver during this test run}
                            {--raw : Display raw HTTP request and response payloads}';

    /**
     * The console command description.
     */
    protected $description = 'Test shipping rate providers and inspect HTTP API payloads locally without the UI';

    public function handle(RateManager $rateManager): int
    {
        $this->info('==============================================');
        $this->info('  Shipping Rate API Local Test Harness');
        $this->info('==============================================');

        $driverOption = $this->option('driver');
        $drivers = $driverOption ? [$driverOption] : $rateManager->selectableDriverNames();

        if ($driverOption && ($this->option('api-key') || $this->option('token'))) {
            $secret = (string) ($this->option('api-key') ?: $this->option('token'));
            config([
                "shipping.providers.{$driverOption}.api_key" => $secret,
                "shipping.providers.{$driverOption}.token" => $secret,
            ]);
            $rateManager->purgeDriver($driverOption);
        }

        // 1. Inspect driver configuration
        $this->info("\n--- Provider Configurations ---");
        $driverConfigRows = [];
        foreach ($rateManager->driverNames() as $name) {
            $config = $rateManager->driverConfig($name);
            $hasKey = ! empty($config['api_key'] ?? $config['token'] ?? null);
            $baseUrl = $config['base_url'] ?? '(default)';
            $isSelectable = in_array($name, $rateManager->selectableDriverNames(), true);

            $driverConfigRows[] = [
                'driver' => $name,
                'selectable' => $isSelectable ? 'Yes' : 'No (env locked)',
                'credentials' => $hasKey ? 'Configured' : ($name === 'fake' ? 'N/A' : '<fg=yellow>Missing</>'),
                'base_url' => $baseUrl,
            ];
        }
        $this->table(['Driver', 'Selectable', 'Credentials', 'Base URL'], $driverConfigRows);

        // 2. Resolve Sender
        $sender = $rateManager->senderFromSettings();
        if ($this->option('sender-cap')) {
            $sender = new RateAddress(
                postalCode: (string) $this->option('sender-cap'),
                city: (string) ($this->option('sender-city') ?: ($sender->city ?: 'Milano')),
                province: (string) ($this->option('sender-prov') ?: ($sender->province ?: 'MI')),
                country: (string) ($this->option('sender-country') ?: ($sender->country ?: 'IT')),
                name: $sender->name ?: 'Shop Sender',
                street: $sender->street ?: 'Via Store 1',
                phone: $sender->phone ?: '021234567',
                email: $sender->email ?: 'store@example.test',
            );
        }

        // 3. Resolve Recipient & Parcels
        $orderArg = $this->option('order');
        $order = null;
        if ($orderArg) {
            $order = Order::where('id', $orderArg)->orWhere('order_number', $orderArg)->first();
            if (! $order) {
                $this->error("Order [{$orderArg}] not found.");

                return self::FAILURE;
            }
            $this->info("\nLoaded order: #{$order->id} ({$order->order_number})");
        }

        if ($order) {
            $rateRequest = $rateManager->requestFromOrder(
                $order,
                [
                    [
                        'weight' => (float) $this->option('weight'),
                        'length' => (float) $this->option('length'),
                        'width' => (float) $this->option('width'),
                        'height' => (float) $this->option('height'),
                    ],
                ],
                $this->option('insurance') ? (float) $this->option('insurance') : null,
                $this->option('cod') ? (float) $this->option('cod') : null,
                null,
                $this->option('carrier'),
            );
            // If sender was overridden on CLI, apply it
            if ($this->option('sender-cap')) {
                $rateRequest = new RateRequest(
                    sender: $sender,
                    recipient: $rateRequest->recipient,
                    parcels: $rateRequest->parcels,
                    insuranceAmount: $rateRequest->insuranceAmount,
                    codAmount: $rateRequest->codAmount,
                    contentDescription: $rateRequest->contentDescription,
                    coupon: $rateRequest->coupon,
                    carrierCode: $rateRequest->carrierCode,
                );
            }
        } else {
            $recipient = new RateAddress(
                postalCode: (string) $this->option('dest-cap'),
                city: (string) $this->option('dest-city'),
                province: (string) $this->option('dest-prov'),
                country: (string) $this->option('dest-country'),
                name: (string) $this->option('dest-name'),
                street: (string) $this->option('dest-street'),
            );

            $parcels = [
                new RateParcel(
                    weightKg: (float) $this->option('weight'),
                    lengthCm: (float) $this->option('length'),
                    widthCm: (float) $this->option('width'),
                    heightCm: (float) $this->option('height'),
                ),
            ];

            $rateRequest = new RateRequest(
                sender: $sender,
                recipient: $recipient,
                parcels: $parcels,
                insuranceAmount: $this->option('insurance') ? (float) $this->option('insurance') : null,
                codAmount: $this->option('cod') ? (float) $this->option('cod') : null,
                contentDescription: 'Merce varia',
                carrierCode: $this->option('carrier'),
            );
        }

        // Summary of Request
        $this->info("\n--- Request Parameters ---");
        $this->line("<fg=cyan>Sender:</>    {$rateRequest->sender->name}, {$rateRequest->sender->street}, {$rateRequest->sender->postalCode} {$rateRequest->sender->city} ({$rateRequest->sender->province}) [{$rateRequest->sender->country}]");
        $this->line("<fg=cyan>Recipient:</> {$rateRequest->recipient->name}, {$rateRequest->recipient->street}, {$rateRequest->recipient->postalCode} {$rateRequest->recipient->city} ({$rateRequest->recipient->province}) [{$rateRequest->recipient->country}]");
        foreach ($rateRequest->parcels as $idx => $p) {
            $dims = "{$p->lengthCm}x{$p->widthCm}x{$p->heightCm} cm";
            $this->line("<fg=cyan>Parcel #".($idx + 1).":</> {$p->weightKg} kg ({$dims}) x {$p->quantity}");
        }

        // 4. HTTP eavesdropping for --raw or -v
        $showRaw = $this->option('raw') || $this->getOutput()->isVerbose();
        if ($showRaw) {
            $this->info("\n--- Live HTTP Traffic ---");
            Event::listen(RequestSending::class, function (RequestSending $event) {
                $this->line("<fg=yellow>>>>> {$event->request->method()} {$event->request->url()}</>");
                $body = $event->request->body();
                if ($body) {
                    $json = json_decode($body, true);
                    $this->line($json ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $body);
                }
            });

            Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
                $status = $event->response->status();
                $color = $event->response->successful() ? 'green' : 'red';
                $this->line("<fg={$color}><<<< HTTP {$status}</>");
                $body = $event->response->body();
                if ($body) {
                    $json = json_decode($body, true);
                    $this->line($json ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $body);
                }
            });
        }

        // 5. Execute Rate Quoting
        $this->info("\n--- Calling Providers (".implode(', ', $drivers).") ---");
        $startTime = microtime(true);
        $result = $rateManager->quoteAll($rateRequest, $drivers);
        $duration = round((microtime(true) - $startTime) * 1000, 1);

        // 6. Display Quotes
        $this->info("\n--- Returned Quotes (".count($result['quotes'])." quotes in {$duration} ms) ---");
        if (! empty($result['quotes'])) {
            $quoteRows = array_map(function (RateQuote $q) {
                return [
                    'provider' => $q->provider,
                    'carrier' => $q->carrierName ?: $q->carrierCode,
                    'service' => $q->serviceName,
                    'net_price' => number_format($q->netPrice, 2).' '.$q->currency,
                    'total_price' => number_format($q->totalPrice, 2).' '.$q->currency,
                    'delivery' => $q->estimatedDelivery ?? 'N/A',
                    'token' => $q->quoteToken ?? 'N/A',
                ];
            }, $result['quotes']);
            $this->table(['Provider', 'Carrier', 'Service', 'Net Price', 'Total Price', 'Est. Delivery', 'Quote Token'], $quoteRows);
        } else {
            $this->warn('No rate quotes were returned.');
        }

        // 7. Display Errors
        if (! empty($result['errors'])) {
            $this->error("\n--- Provider Errors ---");
            $errorRows = [];
            foreach ($result['errors'] as $driverName => $error) {
                $errorRows[] = [
                    'driver' => $driverName,
                    'error' => $error,
                ];
            }
            $this->table(['Driver', 'Error'], $errorRows);
        }

        // 8. Display Balances
        if (! empty($result['balances'])) {
            $this->info("\n--- Account Balances ---");
            $balanceRows = [];
            foreach ($result['balances'] as $driverName => $b) {
                $balanceRows[] = [
                    'driver' => $driverName,
                    'balance' => number_format($b['balance'] ?? 0, 2).' '.($b['currency'] ?? 'EUR'),
                ];
            }
            $this->table(['Driver', 'Prepaid Balance'], $balanceRows);
        }

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
