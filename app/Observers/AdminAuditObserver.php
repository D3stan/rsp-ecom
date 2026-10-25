<?php

namespace App\Observers;

use App\Services\Ops\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Feeds the admin audit trail for the models an admin manages. One observer for all of them: what counts is who did
 * it (AuditLogger skips everything that is not an authenticated admin), not the model.
 */
class AdminAuditObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->audit->record($this->action($model, 'created'), $model);
    }

    public function updated(Model $model): void
    {
        $changes = [];

        foreach ($model->getChanges() as $field => $new) {
            $changes[$field] = [$model->getOriginal($field), $new];
        }

        $this->audit->record($this->action($model, 'updated'), $model, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->audit->record($this->action($model, 'deleted'), $model);
    }

    private function action(Model $model, string $event): string
    {
        return strtolower(class_basename($model)).'.'.$event;
    }
}
