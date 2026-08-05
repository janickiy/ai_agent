<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Представляет таблицу `catalog_news_categories` со справочником тематик новостей.
 *
 * Таблица хранит название, уникальный код, хештег, набор ключевых слов и признак активности.
 * Категории связываются с источниками, результатами анализа и готовыми публикациями.
 */
final class NewsCategory extends NewsModel
{
    protected static string $newsTable = 'categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'is_active' => 'boolean'];
    }

    /**
     * Возвращает источники, для которых разрешена данная тематика, через связующую таблицу каталога.
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, NewsTables::name('source_category'), 'category_id', 'source_id');
    }

    /**
     * Возвращает результаты анализа материалов, классифицированных в данную категорию.
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(ItemAnalysis::class, 'category_id');
    }

    /**
     * Возвращает готовые публикации, относящиеся к данной категории.
     */
    public function publicationPosts(): HasMany
    {
        return $this->hasMany(PublicationPost::class, 'category_id');
    }
}
