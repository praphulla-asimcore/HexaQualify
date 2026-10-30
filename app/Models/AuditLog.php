<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Append-only audit trail (§7). Rows are never updated or deleted. */
class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_log';

    public const UPDATED_AT = null;

    protected $fillable = ['actor_type', 'actor_id', 'action', 'entity', 'entity_id', 'before_json', 'after_json', 'ip'];

    protected function casts(): array
    {
        return ['before_json' => 'array', 'after_json' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('The audit log is append-only.'));
        static::deleting(fn () => throw new \LogicException('The audit log is append-only.'));
    }

    /**
     * Record an action. The actor defaults to the signed-in staff user, else "system".
     */
    public static function record(
        string $action,
        string $entity,
        ?string $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $actorType = null,
        ?string $actorId = null,
    ): self {
        $user = auth()->user();

        return static::create([
            'actor_type' => $actorType ?? ($user ? 'user' : 'system'),
            'actor_id' => $actorId ?? $user?->getAuthIdentifier(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'before_json' => $before,
            'after_json' => $after,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
