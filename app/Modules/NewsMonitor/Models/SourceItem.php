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
    public const MANUAL_PUBLICATION_REASON = 'publication_output_disabled';

    public const PUBLICATION_QUEUED_REASON = 'kaboom_publication_queued';

    public const PUBLICATION_FAILED_REASON = 'kaboom_publication_failed';

    protected static string $newsTable = 'source_items';

    protected $guarded = [];

    /**
     * Преобразует даты и метаданные извлечения исходного материала в прикладные типы.
     *
     * @return array<string, string>
     */
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

    /**
     * Возвращает локальную копию публикации, если материал уже подтверждён Kaboom.
     */
    public function publicationPost(): HasOne
    {
        return $this->hasOne(PublicationPost::class);
    }

    /**
     * Определяет, прошёл ли материал проверки и ожидает ли ручного решения о публикации.
     *
     * Кнопки ручной публикации доступны только материалам, остановленным исключительно
     * выключенной настройкой автоматической отправки постов.
     */
    public function isAwaitingManualPublication(): bool
    {
        return $this->status === 'analyzed'
            && $this->rejection_reason === self::MANUAL_PUBLICATION_REASON;
    }

    /**
     * Определяет, поставлен ли материал в очередь Kaboom и ещё не подтверждён внешним API.
     */
    public function isQueuedForPublication(): bool
    {
        return $this->status === 'accepted'
            && $this->rejection_reason === self::PUBLICATION_QUEUED_REASON;
    }
}
