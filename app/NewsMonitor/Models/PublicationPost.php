<?php

declare(strict_types=1);

namespace App\NewsMonitor\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class);
    }
}
