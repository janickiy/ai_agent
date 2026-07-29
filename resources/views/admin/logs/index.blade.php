@extends('admin.layout')

@section('title', 'Журнал и ошибки')

@section('content')
<div class="row g-3 mb-3">
    @foreach([
        ['label' => 'Событий сегодня', 'value' => $summary['total'], 'class' => 'primary'],
        ['label' => 'Успешно', 'value' => $summary['success'], 'class' => 'success'],
        ['label' => 'Ошибок', 'value' => $summary['error'], 'class' => 'danger'],
        ['label' => 'Отклонено', 'value' => $summary['rejected'], 'class' => 'warning'],
    ] as $metric)
    <div class="col-xl-3 col-sm-6">
        <div class="card border-{{ $metric['class'] }} mb-0">
            <div class="card-body py-3">
                <div class="text-secondary">{{ $metric['label'] }}</div>
                <div class="fs-3 fw-semibold">{{ $metric['value'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <form method="get" action="{{ route('admin.logs.index') }}" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="log-stage">Этап</label>
                <select class="form-select" id="log-stage" name="stage">
                    <option value="">Все этапы</option>
                    @foreach($stages as $value => $label)
                    <option value="{{ $value }}" @selected(request('stage') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="log-status">Статус</label>
                <select class="form-select" id="log-status" name="status">
                    <option value="">Все статусы</option>
                    @foreach($statuses as $value => $status)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="log-date-from">С даты</label>
                <input class="form-control" type="date" id="log-date-from" name="date_from" value="{{ request('date_from') }}">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="log-date-to">По дату</label>
                <input class="form-control" type="date" id="log-date-to" name="date_to" value="{{ request('date_to') }}">
            </div>
            <div class="col-xl-3 col-md-6">
                <label class="form-label" for="log-search">Поиск</label>
                <input class="form-control" type="search" id="log-search" name="search" value="{{ request('search') }}" placeholder="Причина, ошибка или correlation ID">
            </div>
            <div class="col-xl-1 col-md-2 d-flex gap-1">
                <button class="btn btn-primary" type="submit" title="Применить"><i class="bi bi-funnel"></i><span class="visually-hidden">Применить</span></button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.logs.index') }}" title="Сбросить"><i class="bi bi-x-lg"></i><span class="visually-hidden">Сбросить</span></a>
            </div>
        </form>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0" style="min-width: 950px; table-layout: fixed">
            <colgroup>
                <col style="width: 150px">
                <col style="width: 95px">
                <col style="width: 90px">
                <col style="width: 190px">
                <col style="width: 225px">
                <col style="width: 115px">
                <col style="width: 85px">
            </colgroup>
            <thead>
                <tr>
                    <th>Время</th>
                    <th>Этап</th>
                    <th>Статус</th>
                    <th>Источник / материал</th>
                    <th>Результат / ошибка</th>
                    <th>Время выполнения</th>
                    <th>Попытка</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                @php($status = $statuses[$log->status] ?? ['label' => $log->status, 'class' => 'secondary'])
                <tr>
                    <td class="text-nowrap">{{ $log->started_at?->timezone(config('app.display_timezone'))->format('d.m.Y H:i:s') }}</td>
                    <td>
                        <strong>{{ $stages[$log->stage] ?? $log->stage }}</strong>
                        @if(data_get($log->context, 'ai_provider'))<br><small class="text-secondary">AI: {{ data_get($log->context, 'ai_provider') }}</small>@endif
                    </td>
                    <td><span class="badge text-bg-{{ $status['class'] }}">{{ $status['label'] }}</span></td>
                    <td>
                        {{ $log->source?->name ?? ($log->source_id ? 'Источник #'.$log->source_id : '—') }}
                        @if($log->source_item_id)
                        <br><small title="{{ $log->sourceItem?->title_original }}">Материал #{{ $log->source_item_id }}@if($log->sourceItem?->title_original): {{ \Illuminate\Support\Str::limit($log->sourceItem->title_original, 55) }}@endif</small>
                        @endif
                    </td>
                    <td class="source-link">
                        @if($log->error_message)
                        <span class="text-danger" title="{{ $log->error_message }}">{{ \Illuminate\Support\Str::limit($log->error_message, 120) }}</span>
                        @elseif($log->reason_code)
                        {{ $log->reason_code }}
                        @else
                        <span class="text-secondary">—</span>
                        @endif
                        @if($log->error_message && $log->reason_code)<br><small>{{ $log->reason_code }}</small>@endif
                        <br><small class="text-secondary" title="{{ $log->correlation_id }}">{{ \Illuminate\Support\Str::limit($log->correlation_id, 14) }}</small>
                    </td>
                    <td class="text-nowrap">{{ $log->duration_ms === null ? '—' : number_format($log->duration_ms, 0, ',', ' ').' мс' }}</td>
                    <td>{{ $log->attempt }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4">Записей по выбранным условиям нет.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer">{{ $logs->links() }}</div>@endif
</div>
@endsection
