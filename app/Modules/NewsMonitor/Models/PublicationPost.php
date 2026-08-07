<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Представляет таблицу `publishing_publication_posts` с опубликованными новостными постами.
 *
 * Таблица хранит неизменённые заголовок, краткое и полное описания источника, ссылку
 * и имя источника, UID внешней публикации, дату, изображение, категорию, хештеги,
 * результат ответа Kaboom и время успешной публикации.
 */
final class PublicationPost extends NewsModel
{
    protected static string $newsTable = 'posts';

    protected $guarded = [];

    /**
     * Преобразует JSON-поля и временные метки опубликованного поста в прикладные типы.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_published_at' => 'datetime',
            'hashtags' => 'array',
            'validation_meta' => 'array',
            'ready_at' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    /**
     * Возвращает категорию, название которой было отправлено вместе с публикацией в Kaboom.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }
}
