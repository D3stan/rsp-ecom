<?php

namespace App\Jobs;

use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the welcome email to a freshly verified user. Loads the user when it runs,
 * so a user deleted in the meantime is simply skipped.
 */
class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /**
     * Language of the email: captured when the job is dispatched (inside the request), because
     * the worker that sends it has no request and would otherwise use the default locale.
     */
    public string $locale;

    public function __construct(public int $userId, ?string $locale = null)
    {
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

        Mail::to($user->email)->locale($this->locale)->send(new WelcomeEmail($user));

        Log::info('Welcome email sent', ['user_id' => $user->id]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Welcome email permanently failed', [
            'user_id' => $this->userId,
            'error' => $e?->getMessage(),
        ]);
    }
}
