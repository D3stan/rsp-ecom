<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Route::get('/_test/client', fn (Request $request) => response()->json([
        'root' => $request->root(),
        'secure' => $request->isSecure(),
    ]));
});

it('treats the request as https when APP_URL is https in production, even from a public peer', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://shop.test']);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->getJson('http://localhost/_test/client')
        ->assertJsonPath('secure', true)
        ->assertJsonPath('root', fn (string $root) => str_starts_with($root, 'https://'));
});

it('leaves the request on http when APP_URL is http', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'http://shop.test']);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->getJson('http://localhost/_test/client')
        ->assertJsonPath('secure', false);
});

it('does not force https in the testing environment', function () {
    config(['app.url' => 'https://shop.test']);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->getJson('http://localhost/_test/client')
        ->assertJsonPath('secure', false);
});

it('accepts a signed verification link generated as https when the proxy hands PHP a plain http request', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://shop.test']);
    URL::forceScheme('https');
    Log::spy();

    $url = URL::temporarySignedRoute(
        'verification.verify.pending',
        now()->addHour(),
        ['token' => 'sometoken1234567890', 'email' => 'someone@example.test'],
    );

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->get(str_replace('https://', 'http://', $url));

    Log::shouldNotHaveReceived('warning', fn (string $message) => $message === 'Pending verification link rejected: invalid signature');
});
