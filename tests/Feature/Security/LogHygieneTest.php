<?php

namespace Tests\Feature\Security;

use App\Models\Order;
use App\Models\User;
use App\Services\EmailService;
use App\Services\OrderCreationService;
use App\Support\Privacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

class LogHygieneTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        $this->fakeEmailService();

        // Capture everything the application logs during the test in a scratch file
        $this->logFile = storage_path('framework/testing/security-log-hygiene.log');
        @mkdir(dirname($this->logFile), 0777, true);
        @unlink($this->logFile);
        config([
            'logging.default' => 'sec_capture',
            'logging.channels.sec_capture' => ['driver' => 'single', 'path' => $this->logFile, 'level' => 'debug'],
        ]);
        $this->app['log']->forgetChannel('sec_capture');
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);
        $this->tearDownOrderFixtures();
        parent::tearDown();
    }

    private function logged(): string
    {
        return is_file($this->logFile) ? file_get_contents($this->logFile) : '';
    }

    private function stripeReturns(array $session): void
    {
        $this->stripeHttp->responses['/v1/checkout/sessions/'.$session['id']] = $session + ['created' => 1760000000];
    }

    public function test_mask_email(): void
    {
        $this->assertSame('m***@example.com', Privacy::maskEmail('mario.rossi@example.com'));
        $this->assertSame('a***@b.it', Privacy::maskEmail('a@b.it'));
        $this->assertSame('***', Privacy::maskEmail('not-an-address'));
        $this->assertSame('***', Privacy::maskEmail('@example.com'));
        $this->assertSame('', Privacy::maskEmail(null));
        $this->assertSame('', Privacy::maskEmail(''));
    }

    public function test_success_page_logs_no_email_address_or_url_parameters(): void
    {
        $cart = $this->makeCart(guestSessionId: Str::random(40));
        $this->stripeReturns($this->guestSessionPayload($cart));

        $this->get('/checkout/success?session_id=cs_test_123')->assertOk();
        $this->get('/checkout/success?leaky=very-secret-param', ['Referer' => 'https://evil.test/?token=abc'])->assertRedirect();

        $log = $this->logged();
        $this->assertStringContainsString('Retrieved checkout session', $log);
        $this->assertStringNotContainsString('buyer@example.com', $log);
        $this->assertStringNotContainsString('very-secret-param', $log);
        $this->assertStringNotContainsString('evil.test', $log);
    }

    public function test_unexpected_failure_on_the_success_page_logs_class_and_message_but_no_stack_trace(): void
    {
        $cart = $this->makeCart(guestSessionId: Str::random(40));
        $this->stripeReturns($this->guestSessionPayload($cart));

        $failing = \Mockery::mock(OrderCreationService::class);
        $failing->shouldReceive('createFromStripeSession')->andThrow(new \RuntimeException('db down'));
        $this->app->instance(OrderCreationService::class, $failing);
        $this->stripeHttp->responses['/line_items'] = ['object' => 'list', 'data' => [], 'has_more' => false];

        $this->get('/checkout/success?session_id=cs_test_123')->assertOk();

        $log = $this->logged();
        $this->assertStringContainsString('Failed to create order from session in success page', $log);
        $this->assertStringContainsString('RuntimeException', $log);
        $this->assertStringContainsString('db down', $log);
        $this->assertStringContainsString('cs_test_123', $log);
        $this->assertStringNotContainsString('#0 ', $log);
        $this->assertStringNotContainsString('buyer@example.com', $log);
    }

    public function test_email_service_masks_recipients_in_its_log_lines(): void
    {
        (new EmailService)->sendCustomEmail('mario.rossi@example.com', 'Subject', 'Body');

        $log = $this->logged();
        $this->assertStringContainsString('Custom email sent to: m***@example.com', $log);
        $this->assertStringNotContainsString('mario.rossi@example.com', $log);
    }

    public function test_admin_order_update_logs_field_names_but_never_addresses_or_notes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        $this->actingAs($admin)->put(route('admin.orders.update', $order), [
            'notes' => 'gate code 4711, call grandma',
            'billing_address' => json_encode(['address_line_1' => 'Via Segretissima 99', 'email' => 'buyer@example.com']),
        ])->assertRedirect();

        $log = $this->logged();
        $this->assertStringContainsString('Order update attempt', $log);
        $this->assertStringNotContainsString('request_data', $log);
        $this->assertStringNotContainsString('gate code 4711', $log);
        $this->assertStringNotContainsString('Via Segretissima', $log);
        $this->assertStringNotContainsString('buyer@example.com', $log);
    }

    public function test_admin_order_update_failure_logs_class_and_message_but_no_stack_trace(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // a shipped order whose tracking number is added: the shipped email is requested (and blows up)
        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null, 'status' => 'shipped', 'payment_status' => 'succeeded', 'tracking_number' => null]);

        $failing = \Mockery::mock(EmailService::class);
        $failing->shouldReceive('isEnabled')->andReturn(true);
        $failing->shouldReceive('sendOrderShipped')->andThrow(new \RuntimeException('mail backend exploded'));
        $this->app->instance(EmailService::class, $failing);

        $this->actingAs($admin)->put(route('admin.orders.update', $order), ['tracking_number' => 'TRK1'])
            ->assertSessionHas('error');

        $log = $this->logged();
        $this->assertStringContainsString('Failed to update order', $log);
        $this->assertStringContainsString('RuntimeException', $log);
        $this->assertStringContainsString('mail backend exploded', $log);
        $this->assertStringNotContainsString('#0 ', $log);
        $this->assertStringNotContainsString('buyer@example.com', $log);
    }

    public function test_application_code_never_logs_stack_traces_or_raw_request_payloads(): void
    {
        $offenders = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/getTraceAsString\(|[\'"]request_data[\'"]/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders);
    }
}
