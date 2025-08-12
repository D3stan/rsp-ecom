// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    //$schedule->command('app:generate-sitemap')->dailyAt('02:00');
    
    // Clean up expired pending user verifications daily
    $schedule->command('auth:cleanup-pending --force')->dailyAt('03:00');
}
