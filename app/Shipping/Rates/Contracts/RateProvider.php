<?php

namespace App\Shipping\Rates\Contracts;

use App\Shipping\Rates\DTO\RateQuote;
use App\Shipping\Rates\DTO\RateRequest;

interface RateProvider
{
    public function name(): string;

    /**
     * @return list<RateQuote>
     */
    public function quote(RateRequest $request): array;
}
