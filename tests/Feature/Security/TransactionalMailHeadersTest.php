<?php

namespace Tests\Feature\Security;

use App\Mail\ContactMessage;
use App\Mail\NewOrderAlert;
use App\Mail\OrderCancelled;
use App\Mail\OrderConfirmation;
use App\Mail\OrderDelivered;
use App\Mail\OrderRefunded;
use App\Mail\OrderShipped;
use App\Mail\PaidOrderMissingAlert;
use App\Mail\TestEmail;
use App\Mail\WelcomeEmail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Header\Headers;
use Tests\Feature\Mail\Content\Support\EmailFixtures as Fx;
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

    public function test_no_mailable_leaks_internal_ids_through_tag_or_metadata_headers(): void
    {
        $order = Fx::order();
        $user = User::factory()->create(['name' => 'Anna Verdi']);

        $mailables = [
            'confirmation' => new OrderConfirmation($order),
            'shipped' => new OrderShipped($order, 'TRK-123456'),
            'cancelled' => new OrderCancelled($order),
            'delivered' => new OrderDelivered($order),
            'refunded' => new OrderRefunded($order, 10.00),
            'admin new order' => new NewOrderAlert($order),
            'welcome' => new WelcomeEmail($user),
            'contact' => new ContactMessage(['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.test', 'subject' => 'S', 'message' => 'long enough message']),
            'paid order missing' => new PaidOrderMissingAlert('cs_test_abc', ['amount' => '59.00 EUR']),
            'test email' => new TestEmail,
        ];

        foreach ($mailables as $name => $mailable) {
            $headers = Fx::deliver($mailable, 'it')->getHeaders();

            $this->assertSame([], $this->internalHeaderNames($headers), "{$name} must not carry X-Tag / X-Metadata-* headers");
        }
    }

    public function test_no_mailable_declares_tags_or_metadata_on_its_envelope(): void
    {
        foreach (glob(app_path('Mail/*.php')) as $file) {
            $source = file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression('/\b(tags|metadata)\s*:/', $source, basename($file).' declares tags/metadata');
        }
    }

    /** @return array<int, string> */
    private function internalHeaderNames(Headers $headers): array
    {
        return collect($headers->getNames())
            ->filter(fn (string $name) => preg_match('/^(x-tag|x-metadata-.*)$/i', $name))
            ->values()
            ->all();
    }

    public function test_the_header_adding_provider_is_not_registered(): void
    {
        $this->assertNotContains(
            'App\\Providers\\MailServiceProvider',
            array_keys(app()->getLoadedProviders())
        );
    }
}
