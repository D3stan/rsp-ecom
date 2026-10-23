<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Shipping\Labels\LabelManager;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateQuote;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\RateManager;
use App\Shipping\ShippingService;
use App\Shipping\Support\NextWorkingDay;
use App\Shipping\Support\RecipientAddressCheck;
use App\Shipping\Support\ShippingIssue;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShippingQuotesController extends Controller
{
    public function index(RateManager $rates, TrackingManager $tracking): Response
    {
        $sender = $rates->senderFromSettings();

        return Inertia::render('Admin/Shipping/Quotes', [
            'sender' => [
                'postal_code' => $sender->postalCode,
                'city' => $sender->city,
                'province' => $sender->province,
                'country' => $sender->country,
                'name' => $sender->name,
                'street' => $sender->street,
                'phone' => $sender->phone,
                'email' => $sender->email,
            ],
            'carriers' => $tracking->carriers(),
            'rate_drivers' => $rates->selectableDriverNames(),
            'default_parcel_weight_kg' => max(0.01, (float) settings('shipping.default_parcel_weight_kg', 1.0)),
        ]);
    }

    public function quote(Request $request, RateManager $rates): JsonResponse
    {
        $data = $request->validate([
            'sender.postal_code' => 'required|string|max:20',
            'sender.city' => 'required|string|max:100',
            'sender.province' => 'nullable|string|max:20',
            'sender.country' => 'required|string|size:2',
            'sender.name' => 'sometimes|nullable|string|max:100',
            'sender.street' => 'sometimes|nullable|string|max:255',
            'sender.phone' => 'sometimes|nullable|string|max:50',
            'sender.email' => 'sometimes|nullable|string|max:100',
            'recipient.postal_code' => 'required|string|max:20',
            'recipient.city' => 'required|string|max:100',
            'recipient.province' => 'nullable|string|max:20',
            'recipient.country' => 'required|string|size:2',
            'recipient.name' => 'sometimes|nullable|string|max:100',
            'recipient.street' => 'sometimes|nullable|string|max:255',
            'recipient.phone' => 'sometimes|nullable|string|max:50',
            'recipient.email' => 'sometimes|nullable|string|max:100',
            'parcels' => 'required|array|min:1|max:20',
            'parcels.*.weight' => 'required|numeric|min:0.01|max:1000',
            'parcels.*.length' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.width' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.height' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.quantity' => 'nullable|integer|min:1|max:50',
            'insurance_amount' => 'nullable|numeric|min:0|max:1000000',
            'cod_amount' => 'nullable|numeric|min:0|max:1000000',
            'content_description' => 'nullable|string|max:255',
            'coupon' => 'nullable|string|max:100',
            'carrier_code' => 'nullable|string|max:50',
            'drivers' => 'nullable|array',
            'drivers.*' => ['string', Rule::in($rates->driverNames())],
        ]);

        $senderData = $data['sender'];
        $recipientData = $data['recipient'];

        $sender = new RateAddress(
            postalCode: (string) $senderData['postal_code'],
            city: (string) $senderData['city'],
            province: (string) ($senderData['province'] ?? ''),
            country: (string) ($senderData['country'] ?? 'IT'),
            name: (string) ($senderData['name'] ?? $request->input('sender.name', '')),
            street: (string) ($senderData['street'] ?? $request->input('sender.street', '')),
            phone: (string) ($senderData['phone'] ?? $request->input('sender.phone', '')),
            email: (string) ($senderData['email'] ?? $request->input('sender.email', '')),
        );

        $recipient = new RateAddress(
            postalCode: (string) $recipientData['postal_code'],
            city: (string) $recipientData['city'],
            province: (string) ($recipientData['province'] ?? ''),
            country: (string) ($recipientData['country'] ?? 'IT'),
            name: (string) ($recipientData['name'] ?? $request->input('recipient.name', '')),
            street: (string) ($recipientData['street'] ?? $request->input('recipient.street', '')),
            phone: (string) ($recipientData['phone'] ?? $request->input('recipient.phone', '')),
            email: (string) ($recipientData['email'] ?? $request->input('recipient.email', '')),
        );

        $parcels = [];
        foreach ($data['parcels'] as $parcel) {
            $parcels[] = new RateParcel(
                weightKg: (float) $parcel['weight'],
                lengthCm: isset($parcel['length']) && $parcel['length'] !== null && $parcel['length'] !== '' ? (float) $parcel['length'] : null,
                widthCm: isset($parcel['width']) && $parcel['width'] !== null && $parcel['width'] !== '' ? (float) $parcel['width'] : null,
                heightCm: isset($parcel['height']) && $parcel['height'] !== null && $parcel['height'] !== '' ? (float) $parcel['height'] : null,
                quantity: isset($parcel['quantity']) && $parcel['quantity'] !== null ? (int) $parcel['quantity'] : 1,
            );
        }

        $rateRequest = new RateRequest(
            sender: $sender,
            recipient: $recipient,
            parcels: $parcels,
            insuranceAmount: isset($data['insurance_amount']) && $data['insurance_amount'] !== null && $data['insurance_amount'] !== '' ? (float) $data['insurance_amount'] : null,
            codAmount: isset($data['cod_amount']) && $data['cod_amount'] !== null && $data['cod_amount'] !== '' ? (float) $data['cod_amount'] : null,
            contentDescription: ! empty($data['content_description']) ? (string) $data['content_description'] : 'Merce varia',
            coupon: ! empty($data['coupon']) ? (string) $data['coupon'] : null,
            carrierCode: ! empty($data['carrier_code']) ? (string) $data['carrier_code'] : null,
        );

        $result = $rates->quoteAll($rateRequest, $data['drivers'] ?? null);

        return response()->json([
            'quotes' => array_map(fn (RateQuote $q) => $q->toArray(), $result['quotes']),
            'errors' => $result['errors'],
            'balances' => $result['balances'],
        ]);
    }

    public function quoteOrder(Request $request, Order $order, RateManager $rates, TrackingManager $tracking, ShippingService $shipping, LabelManager $labels): JsonResponse
    {
        $data = $request->validate([
            'parcels' => 'sometimes|array|max:20',
            'parcels.*.weight' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.length' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.width' => 'nullable|numeric|min:0|max:1000',
            'parcels.*.height' => 'nullable|numeric|min:0|max:1000',
            'insurance_amount' => 'nullable|numeric|min:0|max:1000000',
            'cod_amount' => 'nullable|numeric|min:0|max:1000000',
            'coupon' => 'nullable|string|max:100',
            'carrier_code' => 'nullable|string|max:50',
            'pickup_date' => ['bail', 'nullable', 'date_format:Y-m-d', function ($attribute, $value, $fail) {
                if ($value !== null && ! NextWorkingDay::isFutureWorkingDay($value)) {
                    $fail('The pickup date must be a future working day.');
                }
            }],
            'drivers' => 'nullable|array',
            'drivers.*' => ['string', Rule::in($rates->driverNames())],
        ]);

        $order->loadMissing('shippingAddress');

        $rateRequest = $rates->requestFromOrder(
            $order,
            $data['parcels'] ?? [],
            isset($data['insurance_amount']) && $data['insurance_amount'] !== null && $data['insurance_amount'] !== '' ? (float) $data['insurance_amount'] : null,
            isset($data['cod_amount']) && $data['cod_amount'] !== null && $data['cod_amount'] !== '' ? (float) $data['cod_amount'] : null,
            $data['coupon'] ?? null,
            $data['carrier_code'] ?? null,
            $data['pickup_date'] ?? null,
        );

        $result = $rates->quoteAll($rateRequest, $data['drivers'] ?? null);

        $customerShippingCost = (float) ($order->shipping_cost ?? $order->shipping_amount ?? 0.0);

        // A quote can be bought here only when its provider has a label driver that ships its carrier (one of ours).
        $labelDrivers = $shipping->labelsEnabled() ? $labels->driverNames() : [];
        $storeCarriers = $tracking->carrierCodes();
        $supported = [];

        $quotes = array_map(function (RateQuote $quote) use ($customerShippingCost, $labelDrivers, $storeCarriers, $labels, &$supported) {
            $quoteArray = $quote->toArray();
            $quoteArray['margin_delta'] = round($customerShippingCost - $quote->totalPrice, 2);
            $buyable = in_array($quote->provider, $labelDrivers, true)
                && in_array($quote->carrierCode, $supported[$quote->provider] ??= $labels->supportedCarriersFor($quote->provider, $storeCarriers), true);
            $quoteArray['store_carrier_code'] = $buyable ? $quote->carrierCode : null;

            return $quoteArray;
        }, $result['quotes']);

        return response()->json([
            'quotes' => $quotes,
            'errors' => $result['errors'],
            'balances' => $result['balances'],
            'issues' => array_map(
                fn (ShippingIssue $issue) => $issue->toArray(),
                [...RecipientAddressCheck::issues($rateRequest->recipient), ...$result['issues']],
            ),
            'customer_shipping_cost' => round($customerShippingCost, 2),
        ]);
    }
}
