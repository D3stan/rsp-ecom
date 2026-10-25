<?php

namespace App\Models;

use App\Enums\OpsEventType;
use Database\Factories\OpsEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per visitor, event type and day (see App\Services\Ops\FunnelTracker). Feeds the funnel checks of
 * App\Services\Ops\InvariantChecker; nothing customer-facing reads it.
 */
class OpsEvent extends Model
{
    /** @use HasFactory<OpsEventFactory> */
    use HasFactory, MassPrunable;

    public const PRUNE_AFTER_DAYS = 90;

    public $timestamps = false;

    protected $fillable = ['type', 'visitor', 'occurred_on', 'created_at'];

    protected $casts = [
        'type' => OpsEventType::class,
        'occurred_on' => 'date',
        'created_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
