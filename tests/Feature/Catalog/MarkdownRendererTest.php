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
})->with(['javascript:alert(1)', 'ftp://example.org/a', 'data:text/html;base64,AAAA', 'javascript:1/alert(1)', '&#106;avascript:alert(1)']);

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

it('keeps root-relative description images and drops absolute and protocol-relative ones', function () {
    expect(markdownRenderer()->toHtml('![x](/storage/products/descriptions/a.png)'))->toContain('src="/storage/products/descriptions/a.png"')
        ->and(markdownRenderer()->toHtml('![x](https://anything.test/storage/products/descriptions/a.png)'))->not->toContain('<img')
        ->and(markdownRenderer()->toHtml('![x](//evil.test/storage/products/descriptions/a.png)'))->not->toContain('<img');
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

it('removes reference-style links with unsafe schemes', function () {
    $html = markdownRenderer()->toHtml("[x][r]\n\n[r]: javascript:alert(1)");

    expect($html)->not->toContain('<a')->not->toContain('href');
});

it('handles autolinks: unsafe schemes are removed, safe ones kept', function () {
    expect(markdownRenderer()->toHtml('<javascript:alert(1)>'))->not->toContain('<a')
        ->and(markdownRenderer()->toHtml('<https://x.test>'))->toContain('href="https://x.test"')->toContain('noopener');
});

it('drops images with javascript or data urls', function (string $url) {
    expect(markdownRenderer()->toHtml("![x]({$url})"))->not->toContain('<img');
})->with(['javascript:alert(1)', 'data:image/png;base64,AAAA']);

it('leaves no empty elements behind when an image is dropped', function () {
    expect(markdownRenderer()->toHtml('![x](https://evil.test/a.png)'))->toBe('');

    $html = markdownRenderer()->toHtml('[![x](https://evil.test/a.png)](https://ok.test)');

    expect($html)->not->toContain('<img')->not->toContain('<a')->not->toContain('<p></p>');
});

it('escapes quotes in image alt text and link titles', function () {
    $base = app(ImageUploadService::class)->descriptionImageBaseUrl();

    $image = markdownRenderer()->toHtml("![x\" onerror=\"alert(1)]({$base}a.png)");
    $link = markdownRenderer()->toHtml('[x](https://x.test "t\" onclick=\"alert(1)")');

    expect($image)->not->toContain('" onerror=')
        ->and($link)->not->toContain('" onclick=');
});

it('strips html blocks', function () {
    expect(markdownRenderer()->toHtml("<div onclick=\"x()\">hi</div>\n\ntext"))->not->toContain('<div');
});

it('demotes headings nested in blockquotes and list items', function () {
    $html = markdownRenderer()->toHtml("> # Quote\n\n- # Item\n- ## Item2");

    expect($html)->not->toContain('<h1')->not->toContain('<h2');
});

it('returns escaped html entities as literal characters in plain text', function () {
    // Callers must escape the plain text for their own output context (meta tags, JSON-LD)
    expect(markdownRenderer()->toPlainText('&lt;b&gt;'))->toBe('<b>');
});
