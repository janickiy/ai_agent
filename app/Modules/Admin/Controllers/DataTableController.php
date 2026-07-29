<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\NewsMonitor\Models\NewsCategory;
use App\NewsMonitor\Models\ProcessingLog;
use App\NewsMonitor\Models\PublicationPost;
use App\NewsMonitor\Models\Source;
use App\NewsMonitor\Models\SourceItem;
use App\NewsMonitor\Support\NewsTables;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

final class DataTableController extends Controller
{
    /** @var list<string> */
    private const ITEM_STATUSES = [
        'discovered',
        'fetched',
        'extracted',
        'analyzed',
        'rejected_irrelevant',
        'rejected_advertising',
        'duplicate',
        'validation_failed',
        'accepted',
    ];

    /** @var array<string, string> */
    private const ITEM_STATUS_CLASSES = [
        'discovered' => 'secondary',
        'fetched' => 'info',
        'extracted' => 'primary',
        'analyzed' => 'warning',
        'rejected_irrelevant' => 'secondary',
        'rejected_advertising' => 'danger',
        'duplicate' => 'dark',
        'validation_failed' => 'danger',
        'accepted' => 'success',
    ];

    public function categories(): JsonResponse
    {
        $query = NewsCategory::query()
            ->withCount('sources');

        return DataTables::eloquent($query)
            ->filterColumn('name', static function (Builder $query, string $keyword): void {
                $query->where(static fn (Builder $filter) => $filter
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%"));
            })
            ->editColumn(
                'keywords',
                static fn (NewsCategory $category): string => implode(', ', $category->keywords ?? []),
            )
            ->addColumn(
                'actions',
                static fn (NewsCategory $category): string => view(
                    'admin.datatables.category-actions',
                    compact('category'),
                )->render(),
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function sources(): JsonResponse
    {
        $query = Source::query()
            ->withCount('items');

        return DataTables::eloquent($query)
            ->setRowClass(static fn (Source $source): string => $source->is_active ? '' : 'table-danger')
            ->addColumn(
                'actions',
                static fn (Source $source): string => view(
                    'admin.datatables.source-actions',
                    compact('source'),
                )->render(),
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function items(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::ITEM_STATUSES)],
        ]);
        $items = NewsTables::name('source_items');
        $sources = NewsTables::name('sources');
        $analyses = NewsTables::name('analyses');
        $categories = NewsTables::name('categories');

        $query = SourceItem::query()
            ->leftJoin("{$sources} as source_table", 'source_table.id', '=', "{$items}.source_id")
            ->leftJoin("{$analyses} as analysis_table", 'analysis_table.source_item_id', '=', "{$items}.id")
            ->leftJoin("{$categories} as category_table", 'category_table.id', '=', 'analysis_table.category_id')
            ->select([
                "{$items}.*",
                'source_table.name as source_name',
                'category_table.name as category_name',
                'analysis_table.category_confidence',
            ])
            ->when(
                isset($filters['status']),
                static fn (Builder $query) => $query->where("{$items}.status", $filters['status']),
            );

        return DataTables::eloquent($query)
            ->filterColumn(
                'title_original',
                static fn (Builder $query, string $keyword) => $query->where(
                    "{$items}.title_original",
                    'like',
                    "%{$keyword}%",
                ),
            )
            ->editColumn(
                'source_published_at',
                fn (SourceItem $item): string => $this->date($item->source_published_at),
            )
            ->addColumn(
                'status_class',
                static fn (SourceItem $item): string => self::ITEM_STATUS_CLASSES[$item->status] ?? 'secondary',
            )
            ->addColumn(
                'actions',
                static fn (SourceItem $item): string => view(
                    'admin.datatables.item-actions',
                    compact('item'),
                )->render(),
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function posts(): JsonResponse
    {
        $posts = NewsTables::name('posts');
        $categories = NewsTables::name('categories');

        $query = PublicationPost::query()
            ->leftJoin("{$categories} as category_table", 'category_table.id', '=', "{$posts}.category_id")
            ->select([
                "{$posts}.*",
                'category_table.name as category_name',
            ]);

        return DataTables::eloquent($query)
            ->filterColumn(
                'title_original',
                static fn (Builder $query, string $keyword) => $query->where(
                    "{$posts}.title_original",
                    'like',
                    "%{$keyword}%",
                ),
            )
            ->editColumn(
                'source_published_at',
                fn (PublicationPost $post): string => $this->date($post->source_published_at),
            )
            ->editColumn(
                'hashtags',
                static fn (PublicationPost $post): string => implode(' ', $post->hashtags ?? []),
            )
            ->toJson();
    }

    public function logs(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'stage' => ['nullable', Rule::in(array_keys(ProcessingLogController::STAGES))],
            'status' => ['nullable', Rule::in(array_keys(ProcessingLogController::STATUSES))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $logs = NewsTables::name('processing_logs');
        $sources = NewsTables::name('sources');
        $items = NewsTables::name('source_items');
        $displayTimezone = (string) config('app.display_timezone');

        $query = ProcessingLog::query()
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

        return DataTables::eloquent($query)
            ->editColumn(
                'started_at',
                fn (ProcessingLog $log): string => $this->date($log->started_at, true),
            )
            ->addColumn(
                'stage_label',
                static fn (ProcessingLog $log): string => ProcessingLogController::STAGES[$log->stage]
                    ?? $log->stage,
            )
            ->addColumn(
                'status_label',
                static fn (ProcessingLog $log): string => ProcessingLogController::STATUSES[$log->status]['label']
                    ?? $log->status,
            )
            ->addColumn(
                'status_class',
                static fn (ProcessingLog $log): string => ProcessingLogController::STATUSES[$log->status]['class']
                    ?? 'secondary',
            )
            ->addColumn(
                'ai_provider',
                static fn (ProcessingLog $log): ?string => data_get($log->context, 'ai_provider'),
            )
            ->filterColumn('source_name', static function (Builder $query, string $keyword): void {
                $query->where(static fn (Builder $filter) => $filter
                    ->where('source_table.name', 'like', "%{$keyword}%")
                    ->orWhere('item_table.title_original', 'like', "%{$keyword}%"));
            })
            ->filterColumn('error_message', static function (Builder $query, string $keyword): void {
                $query->where(static fn (Builder $filter) => $filter
                    ->where('error_message', 'like', "%{$keyword}%")
                    ->orWhere('reason_code', 'like', "%{$keyword}%")
                    ->orWhere('correlation_id', 'like', "%{$keyword}%"));
            })
            ->toJson();
    }

    public function administrators(): JsonResponse
    {
        Gate::authorize('manage-administrators');

        $query = User::query()
            ->where('role', 'administrator');

        return DataTables::eloquent($query)
            ->addColumn(
                'actions',
                static fn (User $administrator): string => view(
                    'admin.datatables.administrator-actions',
                    compact('administrator'),
                )->render(),
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    private function date(mixed $date, bool $withSeconds = false): string
    {
        if ($date === null) {
            return '—';
        }

        return $date
            ->timezone((string) config('app.display_timezone'))
            ->format($withSeconds ? 'd.m.Y H:i:s' : 'd.m.Y H:i');
    }
}
