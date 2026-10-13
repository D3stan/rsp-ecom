<?php

namespace App\Shipping\Tracking\Exceptions;

/**
 * The carrier refused this specific request (unknown number, malformed input): retrying
 * cannot help, so polling for that shipment is switched off until its tracking data changes.
 */
class TrackingRejected extends TrackingException {}
