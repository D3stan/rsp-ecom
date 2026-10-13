<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded, localised messages for the framework's own account notifications (password reset,
 * email verification). They are plugged in with ResetPassword::toMailUsing() / VerifyEmail::toMailUsing()
 * (see User::booted()), so the notification classes themselves stay the framework's.
 *
 * Both notifications are sent synchronously inside the request, so the visitor's locale is the app locale.
 */
class AccountMailMessages
{
    public static function passwordReset(object $notifiable, string $url): MailMessage
    {
        $broker = config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject(__('emails.password_reset.subject', ['app' => config('app.name')]))
            ->view(['emails.password-reset', 'emails.password-reset-text'], [
                'greeting' => self::greeting($notifiable),
                'resetUrl' => $url,
                'expiresInMinutes' => (int) config("auth.passwords.{$broker}.expire", 60),
                'appName' => config('app.name'),
                'logoUrl' => asset('images/rlogo.png'),
            ]);
    }

    public static function verifyEmail(object $notifiable, string $url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('emails.verify_email.subject', ['app' => config('app.name')]))
            ->view(['emails.verify-email', 'emails.verify-email-text'], [
                'greeting' => self::greeting($notifiable),
                'verificationUrl' => $url,
                'appName' => config('app.name'),
                'logoUrl' => asset('images/rlogo.png'),
            ]);
    }

    private static function greeting(object $notifiable): string
    {
        $name = trim((string) ($notifiable->name ?? ''));

        return $name !== ''
            ? __('emails.common.greeting', ['name' => $name])
            : __('emails.common.greeting_generic');
    }
}
