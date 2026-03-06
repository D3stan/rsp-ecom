<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Carbon\Carbon;

class GenerateSitemap extends Command
{
    protected $signature = 'generate:sitemap';
    protected $description = 'Generate comprehensive sitemap.xml with all public pages';

    public function handle(): int
    {
        $this->info('Generating sitemap...');

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

        // Add active categories
        $this->info('Adding categories...');
        Category::where('is_active', true)
            ->withCount(['products' => function ($query) {
                $query->active()->inStock();
            }])
            ->chunk(100, function ($categories) use ($sitemap) {
                foreach ($categories as $category) {
                    // Skip categories with no products
                    if ($category->products_count <= 0) {
                        continue;
                    }
                    $sitemap->add(
                        Url::create("/categories/{$category->slug}")
                            ->setLastModificationDate($category->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.8)
                    );
                }
            });

        // Add active products
        $this->info('Adding products...');
        $productCount = 0;
        Product::active()
            ->inStock()
            ->chunk(100, function ($products) use ($sitemap, &$productCount) {
                foreach ($products as $product) {
                    $sitemap->add(
                        Url::create("/products/{$product->slug}")
                            ->setLastModificationDate($product->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.7)
                    );
                    $productCount++;
                }
            });

        // Write the sitemap to file
        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info("Sitemap generated successfully with {$productCount} products and static pages!");
        $this->info('Sitemap saved to: ' . public_path('sitemap.xml'));
        
        return self::SUCCESS;
    }
}
