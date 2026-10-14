<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_code_never_calls_env_outside_config_files(): void
    {
        $offenders = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if ($file->getExtension() === 'php' && preg_match('/(?<![\w>:$])env\(/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'env() returns null once the config is cached: read config() instead');
    }

    public function test_stripe_keys_come_from_the_cashier_config_and_win_over_the_database_fallback(): void
    {
        Setting::set('stripe_secret_key', 'sk_test_from_db', 'string');
        Setting::set('stripe_public_key', 'pk_test_from_db', 'string');

        config(['cashier.secret' => 'sk_test_from_config', 'cashier.key' => 'pk_test_from_config']);
        $this->assertSame('sk_test_from_config', StripeService::getSecretKey());
        $this->assertSame('pk_test_from_config', StripeService::getPublicKey());

        config(['cashier.secret' => null, 'cashier.key' => null]);
        $this->assertSame('sk_test_from_db', StripeService::getSecretKey());
        $this->assertSame('pk_test_from_db', StripeService::getPublicKey());
        $this->assertTrue(StripeService::isConfigured());
    }

    public function test_the_dev_defaults_do_not_ship_debug_pages_or_a_single_ever_growing_log(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $env);
        $this->assertMatchesRegularExpression('/^LOG_STACK=daily$/m', $env);
        $this->assertMatchesRegularExpression('/^LOG_LEVEL=info$/m', $env);
    }
}
