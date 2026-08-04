<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NewsCategory extends NewsModel
{
    protected static string $newsTable = 'categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'is_active' => 'boolean'];
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, NewsTables::name('source_category'), 'category_id', 'source_id');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(ItemAnalysis::class, 'category_id');
    }

    public function publicationPosts(): HasMany
    {
        return $this->hasMany(PublicationPost::class, 'category_id');
    }
}
