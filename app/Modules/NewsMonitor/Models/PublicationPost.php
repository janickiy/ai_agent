<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

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
