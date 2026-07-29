@extends('admin.layout')

@section('title', 'Открытые источники')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item active" aria-current="page">Источники</li>
@endsection

@section('content')
<div class="card card-primary card-outline shadow-sm sources-card">
    <div class="card-header">
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
            <div>
                <h3 class="card-title mb-1">
                    <i class="bi bi-rss-fill me-2 text-primary" aria-hidden="true"></i>
                    Источники мониторинга
                </h3>
                <p class="mb-0 small text-body-secondary">
                    Управление RSS/Atom-лентами, доверием и периодичностью сбора материалов.
                </p>
            </div>
        @can('manage-sources')
            <div class="card-tools ms-md-auto">
                <a class="btn btn-primary" href="{{ route('admin.sources.create') }}">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
                    Добавить источник
                </a>
            </div>
        @endcan
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered table-hover align-middle source-table mb-0" id="sources-table">
            <thead class="table-light">
                <tr>
                    <th>Источник</th>
                    <th>Тип</th>
                    <th class="text-center">Доверие</th>
                    <th>Лента</th>
                    <th>Период</th>
                    <th class="text-center">Материалов</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('styles')
<style>
    .sources-card .card-header {
        padding: 1rem 1.25rem;
    }
    .sources-card .card-title {
        float: none;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .sources-card .source-table {
        min-width: 1000px;
        table-layout: fixed;
    }
    .sources-card .source-table th:nth-child(1) { width: 21%; }
    .sources-card .source-table th:nth-child(2) { width: 12%; }
    .sources-card .source-table th:nth-child(3) { width: 9%; }
    .sources-card .source-table th:nth-child(4) { width: 9%; }
    .sources-card .source-table th:nth-child(5) { width: 11%; }
    .sources-card .source-table th:nth-child(6) { width: 11%; }
    .sources-card .source-table th:nth-child(7) { width: 14%; }
    .sources-card .source-table th:nth-child(8) { width: 13%; }
    .sources-card table.dataTable > thead > tr > th {
        font-size: .9rem;
        padding-inline: .55rem;
    }
    .sources-card table.dataTable > tbody > tr > td {
        padding-inline: .55rem;
    }
    .source-icon {
        align-items: center;
        background: var(--bs-primary-bg-subtle);
        border: 1px solid var(--bs-primary-border-subtle);
        border-radius: .5rem;
        color: var(--bs-primary-text-emphasis);
        display: inline-flex;
        height: 2.25rem;
        justify-content: center;
        width: 2.25rem;
    }
    .source-domain {
        font-size: .72rem;
        font-weight: 500;
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .source-class-badge {
        font-size: .72rem;
        font-weight: 500;
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .source-items-count {
        min-width: 2.25rem;
    }
    .sources-card .dt-scroll-body {
        border-bottom: 1px solid var(--bs-border-color);
    }
    @media (max-width: 767.98px) {
        .sources-card .card-tools,
        .sources-card .card-tools .btn {
            width: 100%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const escape = window.AdminDataTables.escapeHtml;
    const safeUrl = window.AdminDataTables.safeUrl;
    const sourceClasses = @js(config('news_sources.classes', []));

    window.AdminDataTables.create('#sources-table', {
        ajax: @js(route('admin.datatables.sources')),
        order: [[0, 'asc']],
        scrollX: true,
        columns: [
            {
                data: 'name',
                name: 'name',
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<div class="d-flex align-items-center gap-2">'
                            + '<span class="source-icon flex-shrink-0"><i class="bi bi-globe2" aria-hidden="true"></i></span>'
                            + '<div class="min-w-0"><div class="fw-semibold">' + escape(data) + '</div>'
                            + '<a class="badge rounded-pill text-bg-light border source-domain text-decoration-none" href="' + escape(safeUrl(row.base_url)) + '" target="_blank" rel="noopener noreferrer">'
                            + escape(row.domain) + '<i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a></div>'
                            + '</div>'
                        : data;
                },
            },
            {
                data: 'source_class',
                name: 'source_class',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge text-bg-light border source-class-badge">' + escape(sourceClasses[data] || data) + '</span>'
                        : data;
                },
            },
            {
                data: 'trust_score',
                name: 'trust_score',
                className: 'text-center',
                render: function (data, type) {
                    const score = Number(data);

                    return type === 'display'
                        ? '<span class="badge rounded-pill text-bg-' + (score >= 85 ? 'success' : (score >= 70 ? 'info' : 'warning')) + '">'
                            + '<i class="bi bi-shield-check me-1" aria-hidden="true"></i>' + escape(data) + '%</span>'
                        : data;
                },
            },
            {
                data: 'type',
                name: 'type',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }

                    return row.feed_url
                        ? '<a class="badge text-bg-primary text-decoration-none" href="' + escape(safeUrl(row.feed_url)) + '" target="_blank" rel="noopener noreferrer">'
                            + '<i class="bi bi-rss-fill me-1" aria-hidden="true"></i>' + escape(String(data).toUpperCase()) + '</a>'
                        : '<span class="badge text-bg-light border text-body-secondary">Не указана</span>';
                },
            },
            {
                data: 'poll_interval_minutes',
                name: 'poll_interval_minutes',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="text-nowrap"><i class="bi bi-clock me-1 text-body-secondary" aria-hidden="true"></i>' + escape(data) + ' мин.</span>'
                        : data;
                },
            },
            {
                data: 'items_count',
                name: 'items_count',
                searchable: false,
                className: 'text-center',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge rounded-pill text-bg-info source-items-count">' + escape(data) + '</span>'
                        : data;
                },
            },
            {
                data: 'is_active',
                name: 'is_active',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }

                    const hasError = row.status === 'error';
                    const color = hasError ? 'danger' : (data ? 'success' : 'secondary');
                    const icon = hasError ? 'exclamation-triangle-fill' : (data ? 'check-circle-fill' : 'pause-circle-fill');
                    const label = hasError ? 'Ошибка' : (data ? 'Активен' : 'Отключён');
                    const catalogOnly = row.feed_url === null
                        ? '<div class="small text-body-secondary mt-1"><i class="bi bi-book me-1" aria-hidden="true"></i>Только каталог</div>'
                        : '';

                    return '<span class="badge text-bg-' + color + '"><i class="bi bi-' + icon + ' me-1" aria-hidden="true"></i>' + label + '</span>' + catalogOnly;
                },
            },
            {data: 'actions', name: 'actions', orderable: false, searchable: false},
        ],
    });
});
</script>
@endpush
