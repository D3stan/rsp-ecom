<?php

namespace App\Shipping\Tracking\Contracts;

/**
 * Marker for providers that never contact a carrier (manual links, disabled tracking, ...).
 *
 * The module does not schedule polling for them, and the tracking URL falls back to the manual
 * templates only for providers that are NOT passive. Real adapters must not implement this.
 */
interface PassiveProvider {}
