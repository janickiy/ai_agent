<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

/**
 * Представляет таблицу `system_audit_logs` с историей административных изменений.
 *
 * Таблица хранит пользователя, действие, тип и идентификатор сущности, correlation ID,
 * снимки данных до и после операции, результат и точное время выполнения.
 */
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
