<?php

/*
 * robots.txt is served by the route only: no public/robots.txt may shadow it. Only production is crawlable.
 */

it('lets crawlers index production and points them at the sitemap', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /checkout/')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));
});

it('blocks all crawlers outside production', function () {
    app()->detectEnvironment(fn () => 'development');

    expect($this->get('/robots.txt')->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /");
});

it('has no static robots.txt shadowing the route', function () {
    expect(public_path('robots.txt'))->not->toBeFile();
});
