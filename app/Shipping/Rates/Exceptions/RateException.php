<?php

namespace App\Shipping\Rates\Exceptions;

use App\Shipping\Support\CarriesIssues;
use RuntimeException;

class RateException extends RuntimeException
{
    use CarriesIssues;
}
