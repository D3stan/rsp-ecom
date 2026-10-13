<?php

namespace Tests\Feature\Security;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TransactionalMailHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sent_order_confirmation_has_no_marketing_or_branding_headers(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.test']);

        Mail::to('guest@example.test')->send(new OrderConfirmation($order));

        $transport = app('mail.manager')->mailer()->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());

        $headers = $transport->messages()->first()->getOriginalMessage()->getHeaders();

        $this->assertFalse($headers->has('List-Unsubscribe'), 'transactional mail must not advertise a bogus unsubscribe link');
        $this->assertFalse($headers->has('List-Unsubscribe-Post'));
        $this->assertFalse($headers->has('X-Brand-Logo'));
        $this->assertFalse($headers->has('X-Brand-Name'));
        $this->assertFalse($headers->has('X-Sender-Image'));
    }

    public function test_the_header_adding_provider_is_not_registered(): void
    {
        $this->assertNotContains(
            'App\\Providers\\MailServiceProvider',
            array_keys(app()->getLoadedProviders())
        );
    }
}
