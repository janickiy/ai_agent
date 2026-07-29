<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\NewsMonitor\Models\ProcessingLog;
use Illuminate\View\View;

final class ProcessingLogController extends Controller
{
    /** @var array<string, string> */
    public const STAGES = [
        'discovery' => 'Сбор',
        'fetch' => 'Загрузка',
        'extract' => 'Извлечение',
        'analyze' => 'Анализ',
        'deduplicate' => 'Проверка дублей',
        'decision' => 'Решение',
        'publish' => 'Публикация',
        'pipeline' => 'Pipeline',
    ];

    /** @var array<string, array{label: string, class: string}> */
    public const STATUSES = [
        'success' => ['label' => 'Успешно', 'class' => 'success'],
        'error' => ['label' => 'Ошибка', 'class' => 'danger'],
        'rejected' => ['label' => 'Отклонено', 'class' => 'warning'],
        'pending' => ['label' => 'Ожидание', 'class' => 'info'],
    ];

    public function __invoke(): View
    {
        $displayTimezone = (string) config('app.display_timezone');

        $today = now()->timezone($displayTimezone)->startOfDay()->utc();
        $todayQuery = ProcessingLog::query()->where('started_at', '>=', $today);

        return view('admin.logs.index', [
            'stages' => self::STAGES,
            'statuses' => self::STATUSES,
            'summary' => [
                'total' => (clone $todayQuery)->count(),
                'success' => (clone $todayQuery)->where('status', 'success')->count(),
                'error' => (clone $todayQuery)->where('status', 'error')->count(),
                'rejected' => (clone $todayQuery)->where('status', 'rejected')->count(),
            ],
        ]);
    }
}
