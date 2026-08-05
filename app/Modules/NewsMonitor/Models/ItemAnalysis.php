<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

/**
 * Представляет таблицу `analysis_item_analyses` с результатами анализа исходных материалов.
 *
 * Таблица хранит категорию и актуальность новости, признаки рекламы, оценки уверенности,
 * хештеги, сущности, AI-провайдера, модель, версию промпта, метаданные решения и время проверки.
 */
final class ItemAnalysis extends NewsModel
{
    protected static string $newsTable = 'analyses';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_actual' => 'boolean',
            'is_advertising' => 'boolean',
            'category_confidence' => 'float',
            'ad_confidence' => 'float',
            'hashtags' => 'array',
            'entities' => 'array',
            'decision_meta' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
