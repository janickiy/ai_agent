<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

/**
 * Представляет таблицу `system_processing_logs` с техническим журналом новостного конвейера.
 *
 * Таблица хранит correlation ID, связанные источник, материал и публикацию, этап и статус,
 * номер попытки, длительность, код причины, сообщение ошибки, контекст и время выполнения.
 */
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
