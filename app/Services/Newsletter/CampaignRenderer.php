<?php

namespace App\Services\Newsletter;

use Illuminate\Support\Str;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class CampaignRenderer
{
    private const CSS = <<<'CSS'
        .campaign-body { color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 24px; }
        .campaign-body h1 { margin: 0 0 16px; color: #111111; font-size: 24px; line-height: 30px; }
        .campaign-body h2 { margin: 24px 0 12px; color: #111111; font-size: 20px; line-height: 26px; }
        .campaign-body h3 { margin: 20px 0 10px; color: #111111; font-size: 17px; line-height: 24px; }
        .campaign-body p { margin: 0 0 16px; }
        .campaign-body ul, .campaign-body ol { margin: 0 0 16px; padding-left: 24px; }
        .campaign-body li { margin: 0 0 6px; }
        .campaign-body a { color: #0b3d91; text-decoration: underline; }
        .campaign-body img { max-width: 100%; height: auto; border: 0; }
        .campaign-body blockquote { margin: 0 0 16px; padding: 8px 16px; border-left: 4px solid #e5e5e5; color: #555555; }
        .campaign-body hr { border: 0; border-top: 1px solid #e5e5e5; margin: 24px 0; }
        CSS;

    public function html(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $inlined = (new CssToInlineStyles)->convert('<div class="campaign-body">'.$html.'</div>', self::CSS);

        return preg_match('/<body[^>]*>(.*)<\/body>/s', $inlined, $m) === 1 ? trim($m[1]) : $inlined;
    }

    public function text(string $markdown): string
    {
        return trim($markdown);
    }
}
