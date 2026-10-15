<?php

namespace App\Shipping\Labels\Exceptions;

/**
 * The carrier refused the request (validation, e.g. a bad postal code). The message is safe to show to the admin on
 * the ship form; no order state changes.
 */
class LabelRejected extends LabelException {}
