<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Ops\AbuseMonitor;
use Illuminate\Auth\Events\Failed;

class MonitorFailedLogin
{
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        app(AbuseMonitor::class)->failedLogin(
            request()->ip(),
            is_string($email) ? $email : null,
            is_string($email) && User::query()->where('email', $email)->where('role', 'admin')->exists(),
        );
    }
}
