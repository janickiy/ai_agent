<?php

declare(strict_types=1);

namespace App\NewsMonitor\Models;

final class AuditLog extends NewsModel
{
    protected static string $newsTable = 'audit_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
