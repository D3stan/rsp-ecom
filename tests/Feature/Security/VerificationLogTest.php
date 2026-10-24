<?php

use Illuminate\Support\Facades\Log;

it('does not write the token or the email to the log when a verification link has an invalid signature', function () {
    Log::spy();

    $this->get('/verification/verify/secrettoken1234567890/someone@example.test')->assertRedirect(route('register'));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'Pending verification link rejected: invalid signature'
            && ! str_contains(json_encode($context), 'secrettoken1234567890')
            && ! str_contains(json_encode($context), 'someone@example.test'))
        ->once();
});
