<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use App\Modules\NewsMonitor\Models\ProcessingLog;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Support\NewsTables;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class AdminReadRepository
{
    public function __construct(
        private Source $sources,
        private SourceItem $sourceItems,
        private PublicationPost $publicationPosts,
        private ProcessingLog $processingLogs,
    ) {}

    /**
     * @param string|null $status
     * @return Builder
     */
    public function sourceItemsForDataTable(?string $status = null): Builder
    {
        $items = $this->sourceItems->getTable();
        $sources = $this->sources->getTable();
        $analyses = NewsTables::name('analyses');
        $categories = NewsTables::name('categories');

        $query = $this->sourceItems->newQuery()
            ->leftJoin("{$sources} as source_table", 'source_table.id', '=', "{$items}.source_id")
            ->leftJoin("{$analyses} as analysis_table", 'analysis_table.source_item_id', '=', "{$items}.id")
            ->leftJoin("{$categories} as category_table", 'category_table.id', '=', 'analysis_table.category_id')
            ->select([
                "{$items}.*",
                'source_table.name as source_name',
                'category_table.name as category_name',
                'analysis_table.category_confidence',
            ]);

        if ($status !== null) {
            $query->where("{$items}.status", $status);
        }

        return $query;
    }

    /**
     * @return Builder
     */
    public function publicationPostsForDataTable(): Builder
    {
        $posts = $this->publicationPosts->getTable();
        $categories = NewsTables::name('categories');

        return $this->publicationPosts->newQuery()
            ->leftJoin("{$categories} as category_table", 'category_table.id', '=', "{$posts}.category_id")
            ->where("{$posts}.status", 'exported')
            ->select([
                "{$posts}.*",
                'category_table.name as category_name',
            ]);
    }

    /**
     * @param array $filters
     * @param string $displayTimezone
     * @return Builder
     */
    public function processingLogsForDataTable(array $filters, string $displayTimezone): Builder
    {
        $logs = $this->processingLogs->getTable();
        $sources = $this->sources->getTable();
        $items = $this->sourceItems->getTable();

        return $this->processingLogs->newQuery()
            ->leftJoin("{$sources} as source_table", 'source_table.id', '=', "{$logs}.source_id")
            ->leftJoin("{$items} as item_table", 'item_table.id', '=', "{$logs}.source_item_id")
            ->select([
                "{$logs}.*",
                'source_table.name as source_name',
                'item_table.title_original as source_item_title',
            ])
            ->when(
                isset($filters['stage']),
                static fn (Builder $query) => $query->where("{$logs}.stage", $filters['stage']),
            )
            ->when(
                isset($filters['status']),
                static fn (Builder $query) => $query->where("{$logs}.status", $filters['status']),
            )
            ->when(
                isset($filters['date_from']),
                static fn (Builder $query) => $query->where(
                    "{$logs}.started_at",
                    '>=',
                    CarbonImmutable::parse($filters['date_from'], $displayTimezone)->startOfDay()->utc(),
                ),
            )
            ->when(
                isset($filters['date_to']),
                static fn (Builder $query) => $query->where(
                    "{$logs}.started_at",
                    '<=',
                    CarbonImmutable::parse($filters['date_to'], $displayTimezone)->endOfDay()->utc(),
                ),
            );
    }

    /**
     * @return array{
     *     sources: int,
     *     discovered_today: int,
     *     pending_review: int,
     *     published_today: int,
     *     rejected_today: int,
     *     errors_today: int
     * }
     */
    public function dashboardMetrics(CarbonInterface $today): array
    {
        return [
            'sources' => $this->sources->newQuery()->count(),
            'discovered_today' => $this->sourceItems->newQuery()
                ->where('discovered_at', '>=', $today)
                ->count(),
            'pending_review' => $this->sourceItems->newQuery()
                ->whereIn('status', ['discovered', 'fetched', 'extracted', 'analyzed'])
                ->count(),
            'published_today' => $this->publicationPosts->newQuery()
                ->where('status', 'exported')
                ->where('exported_at', '>=', $today)
                ->count(),
            'rejected_today' => $this->sourceItems->newQuery()
                ->where('updated_at', '>=', $today)
                ->whereIn('status', [
                    'rejected_irrelevant',
                    'rejected_advertising',
                    'validation_failed',
                    'duplicate',
                ])
                ->count(),
            'errors_today' => $this->processingLogs->newQuery()
                ->where('started_at', '>=', $today)
                ->where('status', 'error')
                ->count(),
        ];
    }

    /**
     * @return array{
     *     discovery: int,
     *     fetching: int,
     *     analysis: int,
     *     deduplication: int,
     *     publication: int
     * }
     */
    public function pipelineStageCounts(): array
    {
        return [
            'discovery' => $this->sourceItems->newQuery()->where('status', 'discovered')->count(),
            'fetching' => $this->sourceItems->newQuery()->where('status', 'fetched')->count(),
            'analysis' => $this->sourceItems->newQuery()->where('status', 'extracted')->count(),
            'deduplication' => $this->sourceItems->newQuery()->where('status', 'analyzed')->count(),
            'publication' => $this->publicationPosts->newQuery()
                ->where('status', 'exported')
                ->count(),
        ];
    }

    /** @return Collection<int, object> */
    public function latestEvents(): Collection
    {
        $events = NewsTables::name('events');
        $eventItems = NewsTables::name('event_items');
        $sourceItems = $this->sourceItems->getTable();
        $sources = $this->sources->getTable();

        return DB::table("{$events} as events")
            ->leftJoin(
                "{$eventItems} as event_items",
                'event_items.news_event_id',
                '=',
                'events.id',
            )
            ->leftJoin(
                "{$sourceItems} as source_items",
                'source_items.id',
                '=',
                'event_items.source_item_id',
            )
            ->leftJoin(
                "{$sources} as sources",
                'sources.id',
                '=',
                'source_items.source_id',
            )
            ->select([
                'events.id',
                'events.title',
                'events.event_at',
                'events.created_at',
                DB::raw('MIN(sources.name) as source_name'),
                DB::raw('COUNT(event_items.source_item_id) as items_count'),
            ])
            ->groupBy('events.id', 'events.title', 'events.event_at', 'events.created_at')
            ->orderByRaw('COALESCE(events.event_at, events.created_at) DESC')
            ->limit(10)
            ->get();
    }

    /** @return array{total: int, success: int, error: int, rejected: int} */
    public function processingLogSummarySince(CarbonInterface $since): array
    {
        $query = $this->processingLogs->newQuery()->where('started_at', '>=', $since);

        return [
            'total' => (clone $query)->count(),
            'success' => (clone $query)->where('status', 'success')->count(),
            'error' => (clone $query)->where('status', 'error')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];
    }
}
