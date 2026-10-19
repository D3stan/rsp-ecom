<?php

namespace App\Shipping\Rates\Contracts;

use App\Shipping\Rates\DTO\AccountBalance;

interface InspectsBalance
{
    public function balance(): AccountBalance;
}
