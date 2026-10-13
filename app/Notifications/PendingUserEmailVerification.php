<?php

namespace App\Notifications;

use App\Models\PendingUserVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class PendingUserEmailVerification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * Skip (instead of fail) when the pending verification was cleaned up before the job ran.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Hours the verification link stays valid.
     */
    public const LINK_VALID_HOURS = 24;

    protected PendingUserVerification $pendingUser;

    /**
     * Create a new notification instance.
     */
    public function __construct(PendingUserVerification $pendingUser)
    {
        $this->pendingUser = $pendingUser;
        $this->afterCommit();
        // The queue worker has no request: carry the visitor's language to the moment the mail is built
        $this->locale(app()->getLocale());
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
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
            now()->addHours(self::LINK_VALID_HOURS),
            [
                'token' => $this->pendingUser->verification_token,
                'email' => $this->pendingUser->email,
            ]
        );

        $name = trim((string) $this->pendingUser->name);

        return (new MailMessage)
            ->subject(__('emails.verify_pending.subject', ['app' => config('app.name')]))
            ->view(['emails.pending-user-verification', 'emails.pending-user-verification-text'], [
                'pendingUser' => $this->pendingUser,
                'greeting' => $name !== ''
                    ? __('emails.common.greeting', ['name' => $name])
                    : __('emails.common.greeting_generic'),
                'verificationUrl' => $verificationUrl,
                'expiresInHours' => self::LINK_VALID_HOURS,
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
