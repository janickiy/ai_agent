<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\NewsMonitor\Models\ProcessingLog;
use App\NewsMonitor\Models\PublicationPost;
use App\NewsMonitor\Models\Source;
use App\NewsMonitor\Models\SourceItem;
use App\NewsMonitor\Services\AgentSettings;
use App\NewsMonitor\Support\NewsTables;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(private readonly AgentSettings $settings) {}

    public function __invoke(): View
    {
        $today = now()
            ->timezone((string) config('app.display_timezone'))
            ->startOfDay()
            ->utc();

        $metrics = [
            [
                'label' => 'Источники',
                'value' => Source::query()->count(),
                'class' => 'info',
                'icon' => 'bi-globe2',
            ],
            [
                'label' => 'Найдено сегодня',
                'value' => SourceItem::query()->where('discovered_at', '>=', $today)->count(),
                'class' => 'primary',
                'icon' => 'bi-search',
            ],
            [
                'label' => 'На проверке',
                'value' => SourceItem::query()
                    ->whereIn('status', ['discovered', 'fetched', 'extracted', 'analyzed'])
                    ->count(),
                'class' => 'warning',
                'icon' => 'bi-eye',
            ],
            [
                'label' => 'Опубликовано сегодня',
                'value' => PublicationPost::query()->where('ready_at', '>=', $today)->count(),
                'class' => 'success',
                'icon' => 'bi-check-lg',
            ],
            [
                'label' => 'Отклонено сегодня',
                'value' => SourceItem::query()
                    ->where('updated_at', '>=', $today)
                    ->whereIn('status', [
                        'rejected_irrelevant',
                        'rejected_advertising',
                        'validation_failed',
                        'duplicate',
                    ])
                    ->count(),
                'class' => 'secondary',
                'icon' => 'bi-ban',
            ],
            [
                'label' => 'Ошибки',
                'value' => ProcessingLog::query()
                    ->where('started_at', '>=', $today)
                    ->where('status', 'error')
                    ->count(),
                'class' => 'danger',
                'icon' => 'bi-exclamation-triangle-fill',
            ],
        ];

        $events = $this->latestEvents();
        $agent = [
            'automatic_publication' => $this->settings->automaticPublication(),
            'ai_tokens' => 0,
            'estimated_cost' => 0.0,
            'stages' => [
                'discovery' => SourceItem::query()->where('status', 'discovered')->count(),
                'fetching' => SourceItem::query()->where('status', 'fetched')->count(),
                'analysis' => SourceItem::query()->where('status', 'extracted')->count(),
                'deduplication' => SourceItem::query()->where('status', 'analyzed')->count(),
                'publication' => PublicationPost::query()
                    ->whereIn('status', ['ready', 'reserved'])
                    ->count(),
            ],
        ];

        return view('admin.dashboard', compact('metrics', 'events', 'agent'));
    }

    private function latestEvents(): Collection
    {
        $events = NewsTables::name('events');
        $eventItems = NewsTables::name('event_items');
        $sourceItems = NewsTables::name('source_items');
        $sources = NewsTables::name('sources');

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
}
