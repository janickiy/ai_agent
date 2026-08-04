<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\NewsMonitor\Services\AgentSettings;
use App\Modules\Admin\Repositories\AdminReadRepository;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly AgentSettings $settings,
        private readonly AdminReadRepository $adminReads,
    ) {}

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
            ],
            [
                'label' => 'Найдено сегодня',
                'value' => $metricCounts['discovered_today'],
                'class' => 'primary',
                'icon' => 'bi-search',
            ],
            [
                'label' => 'На проверке',
                'value' => $metricCounts['pending_review'],
                'class' => 'warning',
                'icon' => 'bi-eye',
            ],
            [
                'label' => 'Опубликовано сегодня',
                'value' => $metricCounts['published_today'],
                'class' => 'success',
                'icon' => 'bi-check-lg',
            ],
            [
                'label' => 'Отклонено сегодня',
                'value' => $metricCounts['rejected_today'],
                'class' => 'secondary',
                'icon' => 'bi-ban',
            ],
            [
                'label' => 'Ошибки',
                'value' => $metricCounts['errors_today'],
                'class' => 'danger',
                'icon' => 'bi-exclamation-triangle-fill',
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
