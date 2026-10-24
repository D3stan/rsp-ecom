<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/client', fn (Request $request) => response()->json(['ip' => $request->ip(), 'host' => $request->getHost()]));
});

it('takes the client address the proxy appended, not the one the client sent', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 9.9.9.9'])
        ->getJson('/_test/client')
        ->assertJsonPath('ip', '9.9.9.9');
});

it('ignores forwarded headers from a peer outside the private ranges', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
        ->getJson('/_test/client')
        ->assertJsonPath('ip', '8.8.8.8');
});

it('does not let X-Forwarded-Host change the host', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
        ->getJson('/_test/client')
        ->assertJsonPath('host', fn (string $host) => $host !== 'evil.example');
});
