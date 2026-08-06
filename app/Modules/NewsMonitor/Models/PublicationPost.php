<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

/**
 * Представляет таблицу `publishing_publication_posts` с подготовленными новостными постами.
 *
 * Таблица хранит неизменённые заголовок, краткое и полное описания источника, ссылку
 * и имя источника, дату, изображение, категорию, хештеги, хеш содержимого, статус,
 * результаты валидации и время экспорта.
 */
final class PublicationPost extends NewsModel
{
    protected static string $newsTable = 'posts';

    protected $guarded = [];

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
}
