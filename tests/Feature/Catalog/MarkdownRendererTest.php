<?php

use App\Services\ImageUploadService;
use App\Services\MarkdownRenderer;

function markdownRenderer(): MarkdownRenderer
{
    return app(MarkdownRenderer::class);
}

it('returns an empty string for null or blank input', function () {
    expect(markdownRenderer()->toHtml(null))->toBe('')
        ->and(markdownRenderer()->toHtml("  \n "))->toBe('');
});

it('keeps single newlines as line breaks so legacy plain text looks the same', function () {
    expect(markdownRenderer()->toHtml("Line one\nLine two"))->toContain('Line one<br />');
});

it('strips raw html', function () {
    $html = markdownRenderer()->toHtml('Hi <script>alert(1)</script> <b onclick="x()">bold</b>');

    expect($html)->not->toContain('<script')->not->toContain('<b')->not->toContain('onclick');
});

it('removes links with unsafe schemes but keeps their text', function (string $url) {
    $html = markdownRenderer()->toHtml("[click me]({$url})");

    expect($html)->not->toContain('<a')->not->toContain($url)->toContain('click me');
})->with(['javascript:alert(1)', 'ftp://example.org/a', 'data:text/html;base64,AAAA']);

it('keeps http, mailto and relative links and opens external ones safely', function () {
    $html = markdownRenderer()->toHtml('[a](https://example.org/x) [b](mailto:info@example.org) [c](/products)');

    expect($html)->toContain('href="https://example.org/x"')
        ->toContain('target="_blank"')
        ->toContain('nofollow')
        ->toContain('noopener')
        ->toContain('href="mailto:info@example.org"')
        ->toContain('href="/products"');
});

it('keeps images from the description storage and drops every other image', function () {
    $base = app(ImageUploadService::class)->descriptionImageBaseUrl();

    $kept = markdownRenderer()->toHtml("![photo]({$base}a.png)");
    $dropped = markdownRenderer()->toHtml('![photo](https://evil.test/a.png)');

    expect($kept)->toContain('src="'.$base.'a.png"')->toContain('loading="lazy"')
        ->and($dropped)->not->toContain('<img');
});

it('drops images that use path traversal to leave the description folder', function (string $suffix) {
    $base = app(ImageUploadService::class)->descriptionImageBaseUrl();

    expect(markdownRenderer()->toHtml("![x]({$base}{$suffix})"))->not->toContain('<img');
})->with(['../../private/a.png', '%2e%2e/a.png', '%2E%2E/%2e%2e/a.png', 'a/../../b.png']);

it('demotes headings so the page never gets a second h1', function () {
    $html = markdownRenderer()->toHtml("# Title\n\n## Sub\n\n###### Deep");

    expect($html)->toContain('<h3>Title</h3>')->toContain('<h4>Sub</h4>')->toContain('<h6>Deep</h6>')->not->toContain('<h1');
});

it('produces plain text without markup or markdown syntax', function () {
    $text = markdownRenderer()->toPlainText("**Bold** and [a link](https://x.test)\n\n- one\n- two\n\nFish & chips");

    expect($text)->toBe('Bold and a link one two Fish & chips');
});
