<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Представляет таблицу `collector_source_items` с материалами, обнаруженными в источниках.
 *
 * Таблица хранит исходный и канонический URL, скопированные поля статьи, изображение и дату,
 * контрольные хеши, статус и причину отклонения, метаданные извлечения и временные этапы обработки.
 */
final class SourceItem extends NewsModel
{
    protected static string $newsTable = 'source_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_published_at' => 'datetime',
            'discovered_at' => 'datetime',
            'fetched_at' => 'datetime',
            'extracted_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'extraction_meta' => 'array',
        ];
    }

    /**
     * Возвращает источник, в котором был обнаружен материал.
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Возвращает единственный сохранённый результат AI-анализа материала.
     */
    public function analysis(): HasOne
    {
        return $this->hasOne(ItemAnalysis::class);
    }
}
