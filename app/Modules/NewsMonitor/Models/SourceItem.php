<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function analysis(): HasOne
    {
        return $this->hasOne(ItemAnalysis::class);
    }
}
