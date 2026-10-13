<?php

namespace App\Shipping\Tracking\Contracts;

/**
 * Reserved for a future capability: creating shipments / buying labels through the carrier API.
 *
 * Intentionally empty. It exists so that adapters can already declare the capability and so the
 * eventual contract (createShipment(...): ..., label(...): ...) is added here without touching
 * TrackingProvider. Nothing in the application calls it yet.
 */
interface SupportsShipmentCreation {}
