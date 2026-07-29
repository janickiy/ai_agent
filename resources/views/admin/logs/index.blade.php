@extends('admin.layout')

@section('title', 'Журнал и ошибки')

@section('content')
@php($logsTable = \App\NewsMonitor\Support\NewsTables::name('processing_logs'))
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
        <form class="row g-2 align-items-end" id="logs-filter">
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="log-stage">Этап</label>
                <select class="form-select" id="log-stage">
                    <option value="">Все этапы</option>
                    @foreach($stages as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="log-status">Статус</label>
                <select class="form-select" id="log-status">
                    <option value="">Все статусы</option>
                    @foreach($statuses as $value => $status)
                    <option value="{{ $value }}">{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="log-date-from">С даты</label>
                <input class="form-control" type="date" id="log-date-from">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="log-date-to">По дату</label>
                <input class="form-control" type="date" id="log-date-to">
            </div>
            <div class="col-xl-2 col-md-4 d-flex gap-1">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Применить</button>
                <button class="btn btn-outline-secondary" id="logs-filter-reset" type="button" title="Сбросить"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0" id="logs-table">
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
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const escape = window.AdminDataTables.escapeHtml;
    const filters = {
        stage: document.getElementById('log-stage'),
        status: document.getElementById('log-status'),
        dateFrom: document.getElementById('log-date-from'),
        dateTo: document.getElementById('log-date-to'),
    };
    const table = window.AdminDataTables.create('#logs-table', {
        ajax: {
            url: @js(route('admin.datatables.logs')),
            data: function (request) {
                request.stage = filters.stage.value;
                request.status = filters.status.value;
                request.date_from = filters.dateFrom.value;
                request.date_to = filters.dateTo.value;
            },
        },
        order: [[0, 'desc']],
        scrollX: true,
        columns: [
            {data: 'started_at', name: @js($logsTable.'.started_at')},
            {
                data: 'stage_label',
                name: @js($logsTable.'.stage'),
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<strong>' + escape(data) + '</strong>' + (row.ai_provider ? '<br><small class="text-secondary">AI: ' + escape(row.ai_provider) + '</small>' : '')
                        : data;
                },
            },
            {
                data: 'status_label',
                name: @js($logsTable.'.status'),
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<span class="badge text-bg-' + escape(row.status_class) + '">' + escape(data) + '</span>'
                        : data;
                },
            },
            {
                data: 'source_name',
                name: 'source_name',
                defaultContent: '—',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data || '';
                    }

                    const source = data || (row.source_id ? 'Источник #' + row.source_id : '—');
                    const item = row.source_item_id
                        ? '<br><small>Материал #' + escape(row.source_item_id) + (row.source_item_title ? ': ' + escape(row.source_item_title) : '') + '</small>'
                        : '';

                    return escape(source) + item;
                },
            },
            {
                data: 'error_message',
                name: 'error_message',
                defaultContent: '—',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data || row.reason_code || '';
                    }

                    const result = data
                        ? '<span class="text-danger">' + escape(data) + '</span>'
                        : escape(row.reason_code || '—');
                    const reason = data && row.reason_code ? '<br><small>' + escape(row.reason_code) + '</small>' : '';

                    return result + reason + '<br><small class="text-secondary">' + escape(row.correlation_id) + '</small>';
                },
            },
            {
                data: 'duration_ms',
                name: @js($logsTable.'.duration_ms'),
                render: function (data, type) {
                    return type === 'display' && data !== null
                        ? new Intl.NumberFormat('ru-RU').format(data) + ' мс'
                        : (data === null ? '—' : data);
                },
            },
            {data: 'attempt', name: @js($logsTable.'.attempt')},
        ],
    });

    document.getElementById('logs-filter').addEventListener('submit', function (event) {
        event.preventDefault();
        table.ajax.reload();
    });

    document.getElementById('logs-filter-reset').addEventListener('click', function () {
        Object.values(filters).forEach(function (field) {
            field.value = '';
        });
        table.ajax.reload();
    });
});
</script>
@endpush
