<?php

declare(strict_types=1);

namespace App\NewsMonitor\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class);
    }
}
