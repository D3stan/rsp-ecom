<?php

namespace App\Shipping\Tracking\Exceptions;

/**
 * SHIPPING_TRACKING_DRIVER (or a webhook URL) names a driver that does not exist or may not run here.
 */
class UnknownTrackingDriver extends TrackingException {}
