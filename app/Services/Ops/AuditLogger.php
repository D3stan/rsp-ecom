<?php

namespace App\Services\Ops;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes the admin audit trail. Only an authenticated admin leaves a trace (a customer editing their own profile,
 * the checkout adjusting stock or a queue job are not admin actions) and it never fails the action it records.
 */
class AuditLogger
{
    /** Never stored, even as a changed field name's value */
    private const SECRET_FIELDS = ['password', 'remember_token', 'google_id', 'two_factor_secret', 'two_factor_recovery_codes'];

    private const VALUE_LIMIT = 120;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @param  User|null  $actor  defaults to the signed-in user; a Login listener passes it, as the guard only holds the user once the event is over
     */
    public function record(string $action, ?Model $subject = null, array $changes = [], ?User $actor = null): void
    {
        $actor ??= Auth::user();

        if (! $actor?->isAdmin()) {
            return;
        }

        $sanitized = $this->sanitize($changes);

        // An update that only touched secrets or timestamps (a remember-me token at login) is not worth a line
        if ($changes !== [] && $sanitized === []) {
            return;
        }

        try {
            AuditLog::query()->create([
                'actor_id' => $actor->id,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject ? $this->label($subject) : null,
                'changes' => $sanitized === [] ? null : $sanitized,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit log entry could not be written', ['action' => $action, 'exception' => $e::class]);
        }
    }

    private function label(Model $subject): string
    {
        $label = $subject->name ?? $subject->title ?? $subject->email ?? class_basename($subject).' #'.$subject->getKey();

        return mb_substr((string) $label, 0, 191);
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function sanitize(array $changes): array
    {
        $clean = [];

        foreach ($changes as $field => [$old, $new]) {
            if (in_array($field, self::SECRET_FIELDS, true) || $field === 'updated_at') {
                continue;
            }

            $clean[$field] = [$this->shorten($old), $this->shorten($new)];
        }

        return $clean;
    }

    private function shorten(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return is_string($value) && mb_strlen($value) > self::VALUE_LIMIT ? mb_substr($value, 0, self::VALUE_LIMIT).'…' : $value;
    }
}
