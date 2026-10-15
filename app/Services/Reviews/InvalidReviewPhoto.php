<?php

namespace App\Services\Reviews;

use InvalidArgumentException;

/** The upload is not a JPEG, PNG or WebP image GD can read (whatever its name or claimed type). */
class InvalidReviewPhoto extends InvalidArgumentException {}
