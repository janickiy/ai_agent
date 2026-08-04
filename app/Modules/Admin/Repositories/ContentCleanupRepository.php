<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use App\Modules\NewsMonitor\Models\ProcessingLog;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Support\Facades\DB;

final readonly class ContentCleanupRepository
{
    public function __construct(
        private SourceItem $sourceItems,
        private PublicationPost $publicationPosts,
        private ProcessingLog $processingLogs,
    ) {}

    /**
     * @return array
     * @throws \Throwable
     */
    public function purge(): array
    {
        return DB::transaction(function (): array {
            $counts = [
                'source_items' => $this->sourceItems->newQuery()->count(),
                'posts' => $this->publicationPosts->newQuery()->count(),
                'processing_logs' => $this->processingLogs->newQuery()->count(),
            ];

            foreach ([
                'processing_logs',
                'posts',
                'event_items',
                'duplicates',
                'analyses',
                'source_items',
                'events',
            ] as $table) {
                DB::table(NewsTables::name($table))->delete();
            }

            return $counts;
        });
    }
}
