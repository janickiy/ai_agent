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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Throwable;

final class DashboardController extends Controller
{
    public function __construct(private readonly AgentSettings $settings) {}

    public function __invoke(): View
    {
        $today = now()->utc()->startOfDay();
        $metrics = [
            'Найдено URL' => SourceItem::query()->where('discovered_at', '>=', $today)->count(),
            'Создано статей' => SourceItem::query()->where('created_at', '>=', $today)->count(),
            'Успешно загружено' => SourceItem::query()->where('fetched_at', '>=', $today)->count(),
            'Проанализировано' => SourceItem::query()->where('analyzed_at', '>=', $today)->count(),
            'Отклонено' => SourceItem::query()->where('updated_at', '>=', $today)->whereIn('status', ['rejected_irrelevant', 'rejected_advertising', 'validation_failed'])->count(),
            'Обнаружено дубликатов' => SourceItem::query()->where('updated_at', '>=', $today)->where('status', 'duplicate')->count(),
            'Создано News Events' => DB::table(NewsTables::name('events'))->where('created_at', '>=', $today)->count(),
            'Сгенерировано публикаций' => PublicationPost::query()->where('created_at', '>=', $today)->count(),
            'Опубликовано' => PublicationPost::query()->where('ready_at', '>=', $today)->count(),
            'Завершено с ошибкой' => ProcessingLog::query()->where('created_at', '>=', $today)->where('status', 'error')->count(),
            'Ожидает обработки' => SourceItem::query()->whereIn('status', ['discovered', 'fetched', 'extracted'])->count(),
            'Ожидает публикации' => SourceItem::query()->where('status', 'analyzed')->count(),
            'Среднее время Pipeline' => round((float) ProcessingLog::query()->where('stage', 'pipeline')->whereNotNull('duration_ms')->avg('duration_ms') / 1000, 2).' с',
        ];

        $status = ['label' => 'Работает', 'class' => 'success', 'reasons' => []];
        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            $status = [
                'label' => 'Критическая ошибка',
                'class' => 'danger',
                'reasons' => ['Redis недоступен: Pipeline не может обрабатывать очередь'],
            ];
        }

        if ($status['label'] !== 'Критическая ошибка' && ! $this->settings->collectionEnabled()) {
            $status = ['label' => 'Сбор отключён', 'class' => 'warning', 'reasons' => ['Изменить состояние можно в настройках агента']];
        } elseif ($status['label'] !== 'Критическая ошибка' && ! $this->settings->automaticPublication()) {
            $status = ['label' => 'Публикация отключена', 'class' => 'secondary', 'reasons' => ['Автоматическое создание публикаций выключено']];
        } elseif ($status['label'] !== 'Критическая ошибка') {
            $sourceErrors = Source::query()->where('is_active', true)->where('status', 'error')->count();
            $recentErrors = ProcessingLog::query()->where('created_at', '>=', now()->utc()->subHour())->where('status', 'error')->count();
            if ($sourceErrors > 0 || $recentErrors > 0) {
                $status = [
                    'label' => 'Предупреждение',
                    'class' => 'warning',
                    'reasons' => array_filter([
                        $sourceErrors > 0 ? "Недоступных источников: {$sourceErrors}" : null,
                        $recentErrors > 0 ? "Ошибок за час: {$recentErrors}" : null,
                    ]),
                ];
            }
        }

        return view('admin.dashboard', compact('metrics', 'status'));
    }
}
