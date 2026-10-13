<?php

namespace App\Jobs;

use App\Enums\UserNotificationKind;
use App\Models\User;
use App\Services\OrderCreationService;
use App\Support\Privacy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the framework's password-reset or email-verification notification to an existing user
 * from the queue worker, so the request never waits for SMTP and a failed send is retried.
 *
 * Carries only scalars. The reset token lives in the queue payload until the job has run
 * and is never logged. The user is loaded when the job runs: a deleted user is skipped.
 */
class SendUserNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /**
     * Language of the email: captured when the job is dispatched (inside the request), because
     * the worker has no request and would otherwise use the default locale.
     */
    public string $locale;

    public function __construct(
        public int $userId,
        public UserNotificationKind $kind,
        public ?string $token = null,
        ?string $locale = null,
    ) {
        $locale ??= app()->getLocale();

        $this->locale = in_array($locale, OrderCreationService::SUPPORTED_LOCALES, true) ? $locale : config('app.locale');
        $this->afterCommit();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->email) {
            return;
        }

        if ($this->kind === UserNotificationKind::PasswordReset) {
            if ($this->token === null) {
                return;
            }

            $notification = new ResetPassword($this->token);
        } else {
            if ($user->hasVerifiedEmail()) {
                return;
            }

            $notification = new VerifyEmail;
        }

        $user->notify($notification->locale($this->locale));
    }

    public function failed(?Throwable $e): void
    {
        $user = User::find($this->userId);

        Log::error('Account notification permanently failed', [
            'kind' => $this->kind->value,
            'user_id' => $this->userId,
            'email' => Privacy::maskEmail($user?->email),
            'error' => $e?->getMessage(),
        ]);
    }
}
