<?php

namespace App\Console\Commands;

use App\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PurgeLegacySecrets extends Command
{
    protected $signature = 'settings:purge-legacy-secrets {--force : Do not ask for confirmation}';

    protected $description = 'Delete the Stripe/PayPal keys the old settings page stored in the database (only once they are configured in .env)';

    /** Rows of the old admin page that held secrets, plus every paypal_* row (enabled, client_id, client_secret). */
    private const EXACT_KEYS = ['stripe_secret_key', 'stripe_public_key', 'stripe_webhook_secret'];

    public function handle(): int
    {
        if (blank(config('cashier.secret')) || blank(config('cashier.key'))) {
            $this->error('Refusing to delete: STRIPE_SECRET and STRIPE_KEY are not both set in the environment, and the application may still read the database copies.');
            $this->line('Set them in .env (or the host environment), clear the config cache, then run this command again.');

            return self::FAILURE;
        }

        $keys = DB::table('settings')->orderBy('key')->pluck('key')
            ->filter(fn (string $key) => in_array($key, self::EXACT_KEYS, true) || str_starts_with($key, 'paypal_'))
            ->values()
            ->all();

        if ($keys === []) {
            $this->info('No legacy secret rows found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->line('Rows that will be deleted (values are never shown):');
        foreach ($keys as $key) {
            $this->line("  - {$key}");
        }

        if (! $this->option('force') && ! $this->confirm('Delete these rows permanently?', false)) {
            $this->warn('Aborted, nothing deleted.');

            return self::FAILURE;
        }

        DB::table('settings')->whereIn('key', $keys)->delete();

        foreach ($keys as $key) {
            Cache::forget('setting_'.$key);
        }
        app(Settings::class)->flush();

        $this->info('Deleted '.count($keys).' legacy secret row(s).');

        return self::SUCCESS;
    }
}
