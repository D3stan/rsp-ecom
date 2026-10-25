<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an admin did to the catalogue, the customers and the content, and when admins sign in. Orders keep their own
 * trail (OrderEvent) and settings theirs (SettingChange). Written by App\Services\Ops\AuditLogger.
 *
 * action: admin.login | product.created | product.updated | product.deleted | user.updated | ... (<model>.<event>)
 * changes: {field: [old, new]} for an update, without secrets
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, MassPrunable;

    public const PRUNE_AFTER_DAYS = 365;

    public $timestamps = false;

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'subject_label', 'changes', 'ip_address', 'created_at'];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
