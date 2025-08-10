<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearSitemaps extends Command
{
    protected $signature = 'sitemap:clear {--confirm : Skip confirmation prompt}';
    protected $description = 'Clear all generated sitemap files';

    public function handle(): int
    {
        if (!$this->option('confirm') && !$this->confirm('Are you sure you want to clear all sitemap files?')) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $sitemapFiles = [
            'sitemap.xml',
            'sitemap-main.xml',
            'sitemap-categories.xml'
        ];

        // Find all sitemap-products-*.xml files
        $productSitemaps = glob(public_path('sitemap-products-*.xml'));
        foreach ($productSitemaps as $file) {
            $sitemapFiles[] = basename($file);
        }

        $deletedCount = 0;
        foreach ($sitemapFiles as $file) {
            $filePath = public_path($file);
            if (File::exists($filePath)) {
                File::delete($filePath);
                $deletedCount++;
                $this->info("Deleted: {$file}");
            }
        }

        $this->info("Cleared {$deletedCount} sitemap files.");
        
        return self::SUCCESS;
    }
}
