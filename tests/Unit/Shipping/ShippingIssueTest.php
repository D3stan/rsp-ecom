<?php

use App\Shipping\Rates\Exceptions\RateRejected;
use App\Shipping\Support\ShippingIssue;

it('serialises an issue and rides on a rate exception', function () {
    $issue = new ShippingIssue('spediamo', ShippingIssue::ADDRESS, 'recipient.postal_code', 'invalid', 'Non valido');

    expect($issue->toArray())->toBe([
        'provider' => 'spediamo',
        'kind' => 'address',
        'field' => 'recipient.postal_code',
        'code' => 'invalid',
        'message' => 'Non valido',
    ]);

    $e = (new RateRejected('boom'))->withIssues([$issue]);

    expect($e->issues)->toBe([$issue])->and($e->getMessage())->toBe('boom');
});
