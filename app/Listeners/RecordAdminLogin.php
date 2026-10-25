<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Services\Ops\AuditLogger;
use App\Services\Ops\TelegramNotifier;
use Illuminate\Auth\Events\Login;

/**
 * Every admin sign-in is written to the audit trail; the first one from an address that admin has never used before
 * is also pushed to Telegram (a stolen password shows up as a new address).
 */
class RecordAdminLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user->isAdmin()) {
            return;
        }

        $ip = request()->ip();
        $isNewAddress = $ip !== null && ! AuditLog::query()
            ->where('actor_id', $event->user->getAuthIdentifier())
            ->where('action', 'admin.login')
            ->where('ip_address', $ip)
            ->exists();

        app(AuditLogger::class)->record('admin.login', $event->user, actor: $event->user);

        if ($isNewAddress) {
            app(TelegramNotifier::class)->send("🔑 Admin {$event->user->name} signed in from a new address.");
        }
    }
}
