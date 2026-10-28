<?php

use Illuminate\Console\Scheduling\Schedule;

/*
 * Only the index writes public/sitemap.xml. A weekly single-file sitemap used to write the same path and replace the
 * index (and the chunk files it lists) until the next daily run.
 */

it('schedules only the sitemap index, daily', function () {
    $sitemapEvents = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'generate:sitemap'));

    expect($sitemapEvents)->toHaveCount(1)
        ->and($sitemapEvents->first()->command)->toContain('generate:sitemap-index')
        ->and($sitemapEvents->first()->expression)->toBe('0 2 * * *');
});
