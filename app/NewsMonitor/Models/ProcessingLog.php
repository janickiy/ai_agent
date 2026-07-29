<?php

declare(strict_types=1);

namespace App\NewsMonitor\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProcessingLog extends NewsModel
{
    protected static string $newsTable = 'processing_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'attempt' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class);
    }

    public function publicationPost(): BelongsTo
    {
        return $this->belongsTo(PublicationPost::class);
    }
}
