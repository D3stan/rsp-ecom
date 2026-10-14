<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The home page hero is the largest paint of the shop's landing page: every image it loads (src and srcSet of
 * resources/js/pages/home.tsx, comments ignored) has to exist and stay within a byte budget.
 */
class HeroImagesTest extends TestCase
{
    private const MAX_BYTES = 150 * 1024;

    /** @return array<int, string> public paths ("/images/...") the home page loads */
    private function homeImages(): array
    {
        $source = file_get_contents(resource_path('js/pages/home.tsx'));
        $source = preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $source);

        preg_match_all('~/images/[A-Za-z0-9_./-]+\.(?:png|jpe?g|webp|avif)~', $source, $matches);

        return array_values(array_unique($matches[0]));
    }

    public function test_every_home_page_image_exists_and_is_within_the_budget(): void
    {
        $images = $this->homeImages();
        $this->assertNotEmpty($images, 'the hero should load at least one image');

        foreach ($images as $path) {
            $file = public_path(ltrim($path, '/'));

            $this->assertFileExists($file, "{$path} is referenced by home.tsx but missing");
            $this->assertLessThanOrEqual(
                self::MAX_BYTES,
                filesize($file),
                sprintf('%s weighs %s KB (budget %d KB)', $path, number_format(filesize($file) / 1024), self::MAX_BYTES / 1024),
            );
        }
    }
}
