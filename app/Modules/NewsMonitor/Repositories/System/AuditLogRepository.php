<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\System;

use App\DTO\System\AuditLogData;
use App\Modules\NewsMonitor\Models\AuditLog;
use App\Repositories\BaseRepository;

/** @extends BaseRepository<AuditLog, AuditLogData> */
final class AuditLogRepository extends BaseRepository
{
    public function __construct(AuditLog $model)
    {
        parent::__construct($model);
    }

    protected function modelClass(): string
    {
        return AuditLog::class;
    }

    /** @return non-empty-list<class-string<AuditLogData>> */
    protected function dtoClasses(): array
    {
        return [AuditLogData::class];
    }
}
