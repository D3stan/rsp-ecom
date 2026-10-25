<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Turns admin-written markdown (variant descriptions) into safe HTML and plain text. This is the only class that knows
 * markdown: controllers hand its output to the frontend, so a new frontend only has to print a string.
 */
class MarkdownRenderer
{
    /** Longest markdown text accepted for one description (characters). */
    public const MAX_LENGTH = 10000;

    private const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    private ?MarkdownConverter $converter = null;

    public function __construct(private ImageUploadService $images) {}

    public function toHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return trim($this->converter()->convert($markdown)->getContent());
    }

    /**
     * Text for meta tags, JSON-LD and share sheets: no tags, no markdown syntax, whitespace collapsed.
     */
    public function toPlainText(?string $markdown): string
    {
        $html = preg_replace('#<(?:br|/p|/li|/h[1-6])[^>]*>#i', ' ', $this->toHtml($markdown));
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter !== null) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // Legacy descriptions are plain text with single newlines: keep them as line breaks
            'renderer' => ['soft_break' => "<br />\n"],
            'external_link' => [
                'internal_hosts' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addEventListener(DocumentParsedEvent::class, $this->restrict(...));

        return $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Runs before the external-link processor: unwrap unsafe links, drop foreign images, demote headings.
     */
    private function restrict(DocumentParsedEvent $event): void
    {
        $imageBase = $this->images->descriptionImageBaseUrl();

        // Collect first: the tree must not change while it is being walked
        foreach (iterator_to_array($event->getDocument()->iterator(), false) as $node) {
            if ($node instanceof Heading) {
                $node->setLevel(min(6, $node->getLevel() + 2));
            } elseif ($node instanceof Link && ! $this->isAllowedLink($node->getUrl())) {
                foreach (iterator_to_array($node->children(), false) as $child) {
                    $node->insertBefore($child);
                }
                $node->detach();
            } elseif ($node instanceof Image) {
                if ($this->isDescriptionImage($node->getUrl(), $imageBase)) {
                    $node->data->set('attributes/loading', 'lazy');
                } else {
                    $node->detach();
                }
            }
        }
    }

    /**
     * Under the description folder, and no `..` segment (plain or percent-encoded) that would climb out of it.
     */
    private function isDescriptionImage(string $url, string $imageBase): bool
    {
        return str_starts_with($url, $imageBase) && ! str_contains(rawurldecode($url), '..');
    }

    private function isAllowedLink(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        return ! isset($parts['scheme']) || in_array(strtolower($parts['scheme']), self::ALLOWED_LINK_SCHEMES, true);
    }
}
