<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function seedJob(int $availableMinutesAgo, ?int $reservedAt = null): void
{
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => $reservedAt,
        'available_at' => now()->subMinutes($availableMinutesAgo)->timestamp,
        'created_at' => now()->subMinutes($availableMinutesAgo)->timestamp,
    ]);
}

function seedFailedJobs(int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now(),
        ]);
    }
}

beforeEach(function () {
    config(['queue.default' => 'database']);
});

it('is healthy with an empty queue or a fresh job', function () {
    Log::shouldReceive('critical')->never();
    $this->artisan('ops:check-queue')->expectsOutputToContain('healthy')->assertSuccessful();

    seedJob(2);
    $this->artisan('ops:check-queue')->assertSuccessful();
});

it('logs critical when a runnable job has waited longer than the limit', function () {
    seedJob(11);
    Log::shouldReceive('critical')->once()->withArgs(fn ($m) => str_contains($m, 'queue worker'));

    $this->artisan('ops:check-queue')->assertFailed();
});

it('does not alert for a job waiting out a retry backoff (available in the future)', function () {
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 2, 'reserved_at' => null,
        'available_at' => now()->addMinutes(30)->timestamp, 'created_at' => now()->subHours(2)->timestamp,
    ]);
    Log::shouldReceive('critical')->never();

    $this->artisan('ops:check-queue')->assertSuccessful();
});

it('honors the max-age option and config threshold', function () {
    seedJob(11);
    Log::shouldReceive('critical')->never();
    $this->artisan('ops:check-queue', ['--max-age' => 30])->assertSuccessful();

    Log::shouldReceive('critical')->once();
    config(['mail.queue_alerts.max_job_age_minutes' => 5]);
    $this->artisan('ops:check-queue')->assertFailed();
});

it('logs critical when failed_jobs exceeds the threshold', function () {
    seedFailedJobs(3);
    Log::shouldReceive('critical')->never();
    $this->artisan('ops:check-queue', ['--failed-threshold' => 3])->assertSuccessful();

    Log::shouldReceive('critical')->once()->withArgs(fn ($m) => str_contains($m, '3 failed jobs'));
    $this->artisan('ops:check-queue', ['--failed-threshold' => 2])->assertFailed();
});

it('copes with a missing jobs table by alerting instead of crashing', function () {
    Schema::drop('jobs');
    Log::shouldReceive('critical')->once()->withArgs(fn ($m) => str_contains($m, 'does not exist'));

    $this->artisan('ops:check-queue')->assertFailed();
});

it('copes with a missing failed_jobs table', function () {
    Schema::drop('failed_jobs');
    Log::shouldReceive('critical')->never();

    $this->artisan('ops:check-queue')->assertSuccessful();
});

it('skips the check for the sync queue', function () {
    config(['queue.default' => 'sync']);
    seedJob(120);
    Log::shouldReceive('critical')->never();

    $this->artisan('ops:check-queue')->expectsOutputToContain('nothing to check')->assertSuccessful();
});

it('also alerts on Telegram, once an hour while the problem lasts', function () {
    config(['services.telegram.token' => 'secret-token', 'services.telegram.chat_id' => '4242']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    seedJob(11);

    $this->artisan('ops:check-queue')->assertFailed();
    $this->artisan('ops:check-queue')->assertFailed();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request['text'], 'queue worker'));
});

it('is scheduled every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'ops:check-queue'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/5 * * * *');
});
