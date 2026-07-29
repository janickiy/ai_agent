<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\NewsMonitor\Models\ProcessingLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ProcessingLogController extends Controller
{
    /** @var array<string, string> */
    private const STAGES = [
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
    private const STATUSES = [
        'success' => ['label' => 'Успешно', 'class' => 'success'],
        'error' => ['label' => 'Ошибка', 'class' => 'danger'],
        'rejected' => ['label' => 'Отклонено', 'class' => 'warning'],
        'pending' => ['label' => 'Ожидание', 'class' => 'info'],
    ];

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'stage' => ['nullable', Rule::in(array_keys(self::STAGES))],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'search' => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $displayTimezone = (string) config('app.display_timezone');

        $logs = ProcessingLog::query()
            ->with(['source:id,name', 'sourceItem:id,title_original,canonical_url'])
            ->when(
                isset($filters['stage']),
                static fn (Builder $query) => $query->where('stage', $filters['stage']),
            )
            ->when(
                isset($filters['status']),
                static fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->when($search !== '', static fn (Builder $query) => $query->where(
                static fn (Builder $filter) => $filter
                    ->where('reason_code', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%")
                    ->orWhere('correlation_id', 'like', "%{$search}%"),
            ))
            ->when(
                isset($filters['date_from']),
                static fn (Builder $query) => $query->where(
                    'started_at',
                    '>=',
                    CarbonImmutable::parse($filters['date_from'], $displayTimezone)->startOfDay()->utc(),
                ),
            )
            ->when(
                isset($filters['date_to']),
                static fn (Builder $query) => $query->where(
                    'started_at',
                    '<=',
                    CarbonImmutable::parse($filters['date_to'], $displayTimezone)->endOfDay()->utc(),
                ),
            )
            ->latest('started_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $today = now()->timezone($displayTimezone)->startOfDay()->utc();
        $todayQuery = ProcessingLog::query()->where('started_at', '>=', $today);

        return view('admin.logs.index', [
            'logs' => $logs,
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
