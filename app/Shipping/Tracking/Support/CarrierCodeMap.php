<?php

namespace App\Shipping\Tracking\Support;

use App\Shipping\Support\CarrierCodeMap as BaseCarrierCodeMap;

/**
 * Translates between our provider-neutral carrier codes and a carrier API's courier strings.
 * Extends App\Shipping\Support\CarrierCodeMap for backward compatibility.
 */
final class CarrierCodeMap extends BaseCarrierCodeMap {}
