<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

final class ProcessingLog extends NewsModel
{
    protected static string $newsTable = 'processing_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'attempt' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
