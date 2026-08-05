<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

/**
 * Представляет таблицу `analysis_item_duplicates` с результатами поиска дубликатов.
 *
 * Таблица связывает проверяемый материал с оригиналом и хранит метод сравнения,
 * коэффициент сходства, версию алгоритма и дополнительные диагностические данные.
 */
final class ItemDuplicate extends NewsModel
{
    protected static string $newsTable = 'duplicates';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['similarity' => 'float', 'meta' => 'array'];
    }
}
