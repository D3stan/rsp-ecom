<?php

namespace Tests\Feature\Security;

use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.from.address' => 'noreply@shop.test',
            'mail.contact_email' => 'inbox@shop.test',
        ]);

        // the display name comes from the admin setting store.name
        settings()->set('store.name', 'RSP Shop');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Mario',
            'lastName' => 'Rossi',
            'email' => 'mario@visitor.test',
            'subject' => 'Where is my order?',
            'message' => 'Hello, I would like to know where my order is.',
        ], $overrides);
    }

    private function sentHeaders()
    {
        $transport = app('mail.manager')->mailer()->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());

        return $transport->messages()->first()->getOriginalMessage()->getHeaders();
    }

    public function test_the_mail_is_sent_from_our_own_address_with_the_visitor_as_reply_to(): void
    {
        $this->post('/contact', $this->payload())->assertSessionHasNoErrors();

        $headers = $this->sentHeaders();
        $from = $headers->get('From')->getAddresses()[0];
        $replyTo = $headers->get('Reply-To')->getAddresses()[0];
        $to = $headers->get('To')->getAddresses()[0];

        $this->assertSame('noreply@shop.test', $from->getAddress());
        $this->assertSame('Mario Rossi via RSP Shop', $from->getName());
        $this->assertSame('mario@visitor.test', $replyTo->getAddress());
        $this->assertSame('Mario Rossi', $replyTo->getName());
        $this->assertSame('inbox@shop.test', $to->getAddress());
        $this->assertStringNotContainsString('visitor.test', $from->getAddress());
    }

    public function test_line_breaks_in_subject_and_name_cannot_inject_headers(): void
    {
        $this->post('/contact', $this->payload([
            'firstName' => "Mario\r\nBcc: attacker@evil.test",
            'lastName' => "Rossi\nCc: attacker2@evil.test",
            'subject' => "Hi\r\nBcc: attacker@evil.test\r\nX-Injected: 1",
        ]))->assertSessionHasNoErrors();

        $headers = $this->sentHeaders();

        $this->assertFalse($headers->has('Bcc'));
        $this->assertFalse($headers->has('Cc'));
        $this->assertFalse($headers->has('X-Injected'));
        $this->assertSame(
            'Contact Form: Hi Bcc: attacker@evil.test X-Injected: 1',
            $headers->get('Subject')->getBodyAsString()
        );
        $this->assertStringNotContainsString("\n", $headers->get('Subject')->getBodyAsString());
        $this->assertStringNotContainsString("\n", $headers->get('From')->getBodyAsString());
        $this->assertStringNotContainsString("\n", $headers->get('Reply-To')->getBodyAsString());
    }

    public function test_the_mailable_itself_is_safe_when_built_with_raw_data(): void
    {
        $mail = new ContactMessage([
            'firstName' => "Mar\r\nio",
            'lastName' => 'Rossi <boss@shop.test>',
            'email' => 'mario@visitor.test',
            'subject' => "a\r\nBcc: x@y.z",
            'message' => "line one\nline two",
        ]);

        $envelope = $mail->envelope();

        $this->assertStringNotContainsString("\n", $envelope->subject);
        $this->assertStringNotContainsString('<', $envelope->from->name);
        $this->assertStringNotContainsString('@', $envelope->from->name);
        $this->assertSame('noreply@shop.test', $envelope->from->address);
        $this->assertSame('mario@visitor.test', $envelope->replyTo[0]->address);
        // the message body keeps its line breaks
        $this->assertStringContainsString("line one\nline two", $mail->contactData['message']);
    }

    public function test_html_in_the_message_is_escaped_in_the_email_body(): void
    {
        $this->post('/contact', $this->payload(['message' => '<script>alert(1)</script> and some more text']))
            ->assertSessionHasNoErrors();

        $html = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHtmlBody();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_field_lengths_are_limited(): void
    {
        Mail::fake();

        $this->post('/contact', $this->payload([
            'firstName' => str_repeat('a', 101),
            'lastName' => str_repeat('b', 101),
            'subject' => str_repeat('c', 151),
            'message' => str_repeat('d', 5001),
            'email' => str_repeat('e', 250).'@x.test',
        ]))->assertSessionHasErrors(['firstName', 'lastName', 'subject', 'message', 'email']);

        Mail::assertNothingQueued();
    }

    public function test_a_subject_made_only_of_line_breaks_is_rejected(): void
    {
        Mail::fake();

        $this->post('/contact', $this->payload(['subject' => "\r\n\r\n"]))->assertSessionHasErrors('subject');

        Mail::assertNothingQueued();
    }

    public function test_the_form_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->payload())->assertSessionHasNoErrors();
        }

        $this->post('/contact', $this->payload())->assertStatus(429);

        Mail::assertQueued(ContactMessage::class, 5);
    }
}
