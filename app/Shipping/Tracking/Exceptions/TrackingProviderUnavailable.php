<?php

namespace App\Shipping\Tracking\Exceptions;

/**
 * Temporary failure (timeout, 5xx, rate limit, bad credentials awaiting a fix): retry later.
 */
class TrackingProviderUnavailable extends TrackingException {}
