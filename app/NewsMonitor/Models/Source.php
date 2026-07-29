<?php

declare(strict_types=1);

namespace App\NewsMonitor\Models;

use App\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Source extends NewsModel
{
    protected static string $newsTable = 'sources';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_trusted' => 'boolean',
            'is_allowed' => 'boolean',
            'trust_score' => 'integer',
            'queries' => 'array',
            'last_success_at' => 'datetime',
            'next_poll_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(NewsCategory::class, NewsTables::name('source_category'), 'source_id', 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SourceItem::class);
    }
}
