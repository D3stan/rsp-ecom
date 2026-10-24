<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/client', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
        'host' => $request->getHost(),
        'root' => $request->root(),
        'secure' => $request->isSecure(),
    ]));
});

it('takes the client address the proxy appended, not the one the client sent', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 9.9.9.9'])
        ->getJson('/_test/client')
        ->assertJsonPath('ip', '9.9.9.9');
});

it('trusts an IPv6 unique-local proxy', function () {
    $this->withServerVariables(['REMOTE_ADDR' => 'fd00::5'])
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
        ->assertJsonPath('host', parse_url(config('app.url'), PHP_URL_HOST));
});

it('does not let X-Forwarded-Prefix change the root url', function () {
    $baseline = $this->getJson('/_test/client')->json('root');

    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeaders(['X-Forwarded-Prefix' => '@evil.example'])
        ->getJson('/_test/client')
        ->assertJsonPath('root', $baseline)
        ->assertJsonPath('root', fn (string $root) => ! str_contains($root, 'evil.example'));
});

it('honours X-Forwarded-Proto from a trusted proxy', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->getJson('http://localhost/_test/client')
        ->assertJsonPath('secure', true)
        ->assertJsonPath('root', fn (string $root) => str_starts_with($root, 'https://'));
});

it('ignores X-Forwarded-Proto from an untrusted peer', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->getJson('http://localhost/_test/client')
        ->assertJsonPath('secure', false);
});
