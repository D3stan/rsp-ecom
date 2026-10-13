<?php

namespace Tests\Feature\Shipping\Support;

use App\Models\Order;
use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\TrackingManager;
use Carbon\CarbonImmutable;

class TrackingFixtures
{
    /**
     * A paid, shipped guest order with a tracking number.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function shippedOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'user_id' => null,
            'guest_email' => 'guest@example.test',
            'payment_status' => 'succeeded',
            'status' => 'shipped',
            'tracking_number' => 'TRK-1001',
            'locale' => 'en',
        ], $attributes));
    }

    public static function event(TrackingStatus $status, string $when = '-1 hour', ?string $id = null, string $description = 'Scan', ?string $location = 'Hub A'): TrackingEvent
    {
        return new TrackingEvent(
            status: $status,
            description: $description,
            location: $location,
            occurredAt: CarbonImmutable::parse($when),
            externalId: $id,
        );
    }

    public static function result(TrackingStatus $status, TrackingEvent ...$events): TrackingResult
    {
        return new TrackingResult($status, array_values($events));
    }

    /**
     * Switch the application to the fake driver and return it for scripting.
     */
    public static function useFake(): \App\Shipping\Tracking\Drivers\FakeProvider
    {
        config(['shipping.tracking.driver' => 'fake']);

        return app(TrackingManager::class)->driver('fake');
    }
}
