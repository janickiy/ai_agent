<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\DTO\System\AuditLogData;
use App\Modules\NewsMonitor\Repositories\System\AuditLogRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final readonly class AuditLogger
{
    public function __construct(private AuditLogRepository $auditLogs) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        int $userId,
        string $action,
        Model|string $entity,
        int|string|null $entityId,
        ?array $before,
        ?array $after,
    ): void {
        $this->auditLogs->create(AuditLogData::fromArray([
            'user_id' => $userId,
            'correlation_id' => (string) Str::uuid(),
            'action' => $action,
            'entity_type' => $entity instanceof Model ? $entity::class : $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'before' => $before,
            'after' => $after,
            'result' => 'success',
            'created_at' => now()->utc(),
        ]));
    }
}
