<?php

namespace App\Notifications;

use App\Models\PendingUserVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PendingUserEmailVerification extends Notification
{
    use Queueable;

    protected PendingUserVerification $pendingUser;

    /**
     * Create a new notification instance.
     */
    public function __construct(PendingUserVerification $pendingUser)
    {
        $this->pendingUser = $pendingUser;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify.pending',
            now()->addHours(24),
            [
                'token' => $this->pendingUser->verification_token,
                'email' => $this->pendingUser->email,
            ]
        );

        return (new MailMessage)
            ->subject('Verify Your Email Address - ' . config('app.name'))
            ->view('emails.pending-user-verification', [
                'pendingUser' => $this->pendingUser,
                'verificationUrl' => $verificationUrl,
                'appName' => config('app.name'),
                'logoUrl' => asset('images/rlogo.png'),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pending_user_id' => $this->pendingUser->id,
            'email' => $this->pendingUser->email,
        ];
    }
}
