<?php

namespace App\Shipping\Support;

trait CarriesIssues
{
    /** @var list<ShippingIssue> */
    public array $issues = [];

    /** @param list<ShippingIssue> $issues */
    public function withIssues(array $issues): static
    {
        $this->issues = $issues;

        return $this;
    }
}
