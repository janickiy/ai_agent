<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Repositories\AdminReadRepository;
use App\Modules\NewsMonitor\Services\AgentSettings;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    /**
     * Получает сервис настроек агента и репозиторий агрегированных данных,
     * необходимые для построения главной страницы административной панели.
     */
    public function __construct(
        private readonly AgentSettings $settings,
        private readonly AdminReadRepository $adminReads,
    ) {}

    /**
     * Формирует и отображает сводную панель состояния агента.
     *
     * Метод рассчитывает начало текущего дня в отображаемом часовом поясе,
     * получает метрики, последние события и состояние этапов обработки.
     */
    public function __invoke(): View
    {
        $today = now()
            ->timezone((string) config('app.display_timezone'))
            ->startOfDay()
            ->utc();
        $metricCounts = $this->adminReads->dashboardMetrics($today);

        $metrics = [
            [
                'label' => 'Источники',
                'value' => $metricCounts['sources'],
                'class' => 'info',
                'icon' => 'bi-globe2',
                'url' => route('admin.sources.index'),
            ],
            [
                'label' => 'Найдено сегодня',
                'value' => $metricCounts['discovered_today'],
                'class' => 'primary',
                'icon' => 'bi-search',
                'url' => route('admin.items.index'),
            ],
            [
                'label' => 'На проверке',
                'value' => $metricCounts['pending_review'],
                'class' => 'warning',
                'icon' => 'bi-eye',
                'url' => route('admin.items.index'),
            ],
            [
                'label' => 'Опубликовано сегодня',
                'value' => $metricCounts['published_today'],
                'class' => 'success',
                'icon' => 'bi-check-lg',
                'url' => route('admin.posts.index'),
            ],
            [
                'label' => 'Отклонено сегодня',
                'value' => $metricCounts['rejected_today'],
                'class' => 'secondary',
                'icon' => 'bi-ban',
                'url' => route('admin.items.index'),
            ],
            [
                'label' => 'Ошибки',
                'value' => $metricCounts['errors_today'],
                'class' => 'danger',
                'icon' => 'bi-exclamation-triangle-fill',
                'url' => route('admin.logs.index'),
            ],
        ];

        $events = $this->adminReads->latestEvents();
        $agent = [
            'automatic_publication' => $this->settings->automaticPublication(),
            'ai_tokens' => 0,
            'estimated_cost' => 0.0,
            'stages' => $this->adminReads->pipelineStageCounts(),
        ];

        return view('admin.dashboard', compact('metrics', 'events', 'agent'));
    }
}
