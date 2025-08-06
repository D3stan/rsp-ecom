<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Hook into the mail sending process to add custom headers
        Event::listen(MessageSending::class, function (MessageSending $event) {
            // Add custom headers that some email clients use for sender branding
            $event->message->getHeaders()->addTextHeader('X-Brand-Logo', asset('images/rlogo.png'));
            $event->message->getHeaders()->addTextHeader('X-Brand-Name', config('app.name'));
            $event->message->getHeaders()->addTextHeader('X-Sender-Image', asset('images/rlogo.png'));
            
            // Add List-Unsubscribe header for better deliverability
            if (config('app.url')) {
                $event->message->getHeaders()->addTextHeader('List-Unsubscribe', '<' . config('app.url') . '/unsubscribe>');
            }
        });
    }
}
