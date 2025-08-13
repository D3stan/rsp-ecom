<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;
use Carbon\Carbon;

class GenerateSitemapIndex extends Command
{
    protected $signature = 'generate:sitemap-index {--force : Force regeneration even if files exist}';
    protected $description = 'Generate sitemap index with separate sitemaps for different content types';

    public function handle(): int
    {
        $this->info('Generating sitemap index...');

        $sitemapIndex = SitemapIndex::create();
        $baseUrl = config('app.url');

        // Generate main sitemap for static pages
        $this->generateMainSitemap();
        $sitemapIndex->add('/sitemap-main.xml');

        // Generate categories sitemap
        $this->generateCategoriesSitemap();
        $sitemapIndex->add('/sitemap-categories.xml');

        // Generate products sitemaps (split if too many products)
        $productCount = Product::active()->inStock()->count();
        $maxProductsPerSitemap = 5000; // Google recommends max 50,000 URLs per sitemap

        if ($productCount > $maxProductsPerSitemap) {
            $sitemapCount = ceil($productCount / $maxProductsPerSitemap);
            for ($i = 1; $i <= $sitemapCount; $i++) {
                $this->generateProductsSitemap($i, $maxProductsPerSitemap);
                $sitemapIndex->add("/sitemap-products-{$i}.xml");
            }
        } else {
            $this->generateProductsSitemap(1, $maxProductsPerSitemap);
            $sitemapIndex->add('/sitemap-products-1.xml');
        }

        // Write sitemap index
        $sitemapIndexPath = public_path('sitemap.xml');
        $sitemapIndex->writeToFile($sitemapIndexPath);

        $this->info("Sitemap index generated with {$productCount} products across multiple sitemaps!");
        $this->info('Sitemap index saved to: ' . $sitemapIndexPath);

        // Update robots.txt
        $this->updateRobotsTxt($baseUrl . '/sitemap.xml');

        return self::SUCCESS;
    }

    private function generateMainSitemap(): void
    {
        $this->info('Generating main sitemap...');
        
        $sitemap = Sitemap::create();

        // Add homepage
        $sitemap->add(
            Url::create('/')
                ->setLastModificationDate(Carbon::now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0)
        );

        // Add static pages
        $staticPages = [
            '/products' => [
                'frequency' => Url::CHANGE_FREQUENCY_HOURLY,
                'priority' => 0.9
            ],
            '/about' => [
                'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.7
            ],
            '/contact' => [
                'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.6
            ],
            '/privacy' => [
                'frequency' => Url::CHANGE_FREQUENCY_YEARLY,
                'priority' => 0.3
            ],
            '/terms' => [
                'frequency' => Url::CHANGE_FREQUENCY_YEARLY,
                'priority' => 0.3
            ],
            '/shipping-returns' => [
                'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.5
            ],
            '/faq' => [
                'frequency' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.5
            ],
        ];

        foreach ($staticPages as $url => $config) {
            $sitemap->add(
                Url::create($url)
                    ->setLastModificationDate(Carbon::now())
                    ->setChangeFrequency($config['frequency'])
                    ->setPriority($config['priority'])
            );
        }

        $sitemap->writeToFile(public_path('sitemap-main.xml'));
    }

    private function generateCategoriesSitemap(): void
    {
        $this->info('Generating categories sitemap...');
        
        $sitemap = Sitemap::create();

        Category::where('is_active', true)
            ->withCount(['products' => function ($query) {
                $query->active()->inStock();
            }])
            ->having('products_count', '>', 0)
            ->chunk(100, function ($categories) use ($sitemap) {
                foreach ($categories as $category) {
                    $sitemap->add(
                        Url::create("/categories/{$category->slug}")
                            ->setLastModificationDate($category->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.8)
                    );
                }
            });

        $sitemap->writeToFile(public_path('sitemap-categories.xml'));
    }

    private function generateProductsSitemap(int $page, int $limit): void
    {
        $this->info("Generating products sitemap {$page}...");
        
        $sitemap = Sitemap::create();
        $offset = ($page - 1) * $limit;

        Product::active()
            ->inStock()
            ->offset($offset)
            ->limit($limit)
            ->chunk(100, function ($products) use ($sitemap) {
                foreach ($products as $product) {
                    $sitemap->add(
                        Url::create("/products/{$product->slug}")
                            ->setLastModificationDate($product->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.7)
                    );
                }
            });

        $sitemap->writeToFile(public_path("sitemap-products-{$page}.xml"));
    }

    private function updateRobotsTxt(string $sitemapUrl): void
    {
        $this->info('Updating robots.txt...');
        $robotsTxtPath = public_path('robots.txt');

        if (!File::exists($robotsTxtPath)) {
            $this->warn('robots.txt not found. Creating a new one.');
            File::put($robotsTxtPath, "User-agent: *\nSitemap: {$sitemapUrl}");
            return;
        }

        $content = File::get($robotsTxtPath);
        $sitemapLine = "Sitemap: {$sitemapUrl}";

        // Check if sitemap entry already exists
        if (str_contains($content, 'Sitemap:')) {
            // Replace existing sitemap entry
            $content = preg_replace('/Sitemap: .*/', $sitemapLine, $content);
        } else {
            // Add new sitemap entry
            $content = rtrim($content) . "\n\n{$sitemapLine}";
        }
        
        // Replace the placeholder comment if it exists
        $content = str_replace('# Sitemap location will be dynamically generated', '', $content);

        File::put($robotsTxtPath, trim($content) . "\n");
        $this->info('robots.txt updated successfully.');
    }
}