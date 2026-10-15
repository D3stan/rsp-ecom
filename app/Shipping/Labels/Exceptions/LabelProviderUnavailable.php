<?php

namespace App\Shipping\Labels\Exceptions;

/**
 * A transient or infrastructure problem (timeout, 5xx, auth, missing token). The admin sees an error flash with a
 * reference id and nothing changes; they can retry.
 */
class LabelProviderUnavailable extends LabelException {}
