<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ClearAllCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-all-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all application caches (application, config, route, view, and event)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing all application caches...');

        Artisan::call('cache:clear');
        $this->info('Application cache cleared.');

        Artisan::call('config:clear');
        $this->info('Configuration cache cleared.');

        Artisan::call('route:clear');
        $this->info('Route cache cleared.');

        Artisan::call('view:clear');
        $this->info('View cache cleared.');

        Artisan::call('event:clear');
        $this->info('Event cache cleared.');

        $this->info('All caches cleared successfully!');

        return 0;
    }
}
