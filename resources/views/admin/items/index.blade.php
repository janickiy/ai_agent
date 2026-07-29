@extends('admin.layout')

@section('title', 'Исходные публикации')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item active" aria-current="page">Материалы</li>
@endsection

@section('content')
@php
    $itemsTable = \App\NewsMonitor\Support\NewsTables::name('source_items');
    $itemStatusLabels = [
        'discovered' => 'Обнаружен',
        'fetched' => 'Загружен',
        'extracted' => 'Извлечён',
        'analyzed' => 'Проанализирован',
        'rejected_irrelevant' => 'Нерелевантен',
        'rejected_advertising' => 'Реклама',
        'duplicate' => 'Дубликат',
        'validation_failed' => 'Ошибка проверки',
        'accepted' => 'Принят',
    ];
    $itemStatusIcons = [
        'discovered' => 'search',
        'fetched' => 'cloud-arrow-down-fill',
        'extracted' => 'file-earmark-text-fill',
        'analyzed' => 'cpu-fill',
        'rejected_irrelevant' => 'slash-circle-fill',
        'rejected_advertising' => 'megaphone-fill',
        'duplicate' => 'copy',
        'validation_failed' => 'exclamation-triangle-fill',
        'accepted' => 'check-circle-fill',
    ];
@endphp

<div class="card card-primary card-outline shadow-sm items-card">
    <div class="card-header">
        <div>
            <h3 class="card-title mb-1">
                <i class="bi bi-newspaper me-2 text-primary" aria-hidden="true"></i>
                Материалы мониторинга
            </h3>
            <p class="mb-0 small text-body-secondary">
                Исходные публикации, полученные из подключённых RSS/Atom-источников.
            </p>
        </div>
    </div>

    <div class="card-body border-bottom bg-body-tertiary py-3">
        <form class="row g-2 align-items-end" id="items-filter">
            <div class="col-sm-7 col-md-5 col-lg-4 col-xl-3">
                <label class="form-label fw-semibold" for="item-status">Статус материала</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-funnel" aria-hidden="true"></i></span>
                    <select class="form-select" id="item-status">
                        <option value="">Все статусы</option>
                        @foreach($itemStatusLabels as $status => $label)
                        <option value="{{ $status }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-sm-auto">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                    Применить
                </button>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <table class="table table-bordered table-hover align-middle item-table mb-0" id="items-table">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Дата публикации</th>
                    <th>Добавлено</th>
                    <th>Обновлено</th>
                    <th>Источник</th>
                    <th>Заголовок</th>
                    <th>Категория</th>
                    <th>Статус / причина</th>
                    <th>Действия</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('styles')
<style>
    .items-card .card-header {
        padding: 1rem 1.25rem;
    }
    .items-card .card-title {
        float: none;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .items-card .item-table {
        min-width: 1500px;
        table-layout: fixed;
    }
    .items-card .item-table th:nth-child(1) { width: 5%; }
    .items-card .item-table th:nth-child(2) { width: 11%; }
    .items-card .item-table th:nth-child(3) { width: 11%; }
    .items-card .item-table th:nth-child(4) { width: 11%; }
    .items-card .item-table th:nth-child(5) { width: 10%; }
    .items-card .item-table th:nth-child(6) { width: 24%; }
    .items-card .item-table th:nth-child(7) { width: 11%; }
    .items-card .item-table th:nth-child(8) { width: 12%; }
    .items-card .item-table th:nth-child(9) { width: 5%; }
    .items-card table.dataTable > thead > tr > th {
        font-size: .9rem;
        padding-inline: .65rem;
    }
    .items-card table.dataTable > tbody > tr > td {
        padding-inline: .65rem;
    }
    .item-id {
        min-width: 2.5rem;
    }
    .item-date {
        align-items: center;
        display: inline-flex;
        gap: .4rem;
        white-space: nowrap;
    }
    .item-source {
        font-size: .75rem;
        font-weight: 500;
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .item-title-link {
        display: block;
        font-weight: 600;
        line-height: 1.35;
        text-decoration: none;
    }
    .item-url {
        display: block;
        font-size: .72rem;
        margin-top: .3rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .item-category {
        font-size: .75rem;
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .item-status-reason {
        line-height: 1.25;
        overflow-wrap: anywhere;
    }
    .items-card .dt-scroll-body {
        border-bottom: 1px solid var(--bs-border-color);
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const escape = window.AdminDataTables.escapeHtml;
    const safeUrl = window.AdminDataTables.safeUrl;
    const status = document.getElementById('item-status');
    const statusLabels = @js($itemStatusLabels);
    const statusIcons = @js($itemStatusIcons);

    function renderDate(data, type, icon) {
        if (type !== 'display') {
            return data || '';
        }

        return data
            ? '<span class="item-date"><i class="bi bi-' + icon + ' text-body-secondary" aria-hidden="true"></i>' + escape(data) + '</span>'
            : '<span class="text-body-secondary">—</span>';
    }

    const table = window.AdminDataTables.create('#items-table', {
        ajax: {
            url: @js(route('admin.datatables.items')),
            data: function (request) {
                request.status = status.value;
            },
        },
        order: [[1, 'desc']],
        scrollX: true,
        columns: [
            {
                data: 'id',
                name: @js($itemsTable.'.id'),
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge rounded-pill text-bg-light border item-id">#' + escape(data) + '</span>'
                        : data;
                },
            },
            {
                data: 'source_published_at',
                name: @js($itemsTable.'.source_published_at'),
                render: function (data, type) {
                    return renderDate(data, type, 'calendar-event');
                },
            },
            {
                data: 'created_at',
                name: @js($itemsTable.'.created_at'),
                render: function (data, type) {
                    return renderDate(data, type, 'plus-circle');
                },
            },
            {
                data: 'updated_at',
                name: @js($itemsTable.'.updated_at'),
                render: function (data, type) {
                    return renderDate(data, type, 'arrow-repeat');
                },
            },
            {
                data: 'source_name',
                name: 'source_table.name',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge text-bg-light border item-source"><i class="bi bi-rss me-1 text-primary" aria-hidden="true"></i>' + escape(data || '—') + '</span>'
                        : data;
                },
            },
            {
                data: 'title_original',
                name: 'title_original',
                render: function (data, type, row) {
                    const title = data || row.canonical_url;

                    return type === 'display'
                        ? '<a class="item-title-link" href="' + escape(safeUrl(row.canonical_url)) + '" target="_blank" rel="noopener noreferrer">'
                            + escape(title) + '<i class="bi bi-box-arrow-up-right ms-1 small" aria-hidden="true"></i></a>'
                            + '<span class="item-url text-body-secondary" title="' + escape(row.canonical_url || '') + '">' + escape(row.canonical_url || '') + '</span>'
                        : title;
                },
            },
            {
                data: 'category_name',
                name: 'category_table.name',
                defaultContent: '—',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data || '';
                    }

                    const confidence = row.category_confidence === null
                        ? ''
                        : '<span class="badge rounded-pill text-bg-info ms-1">' + Number(row.category_confidence).toFixed(2) + '</span>';

                    return data
                        ? '<span class="badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle item-category">'
                            + '<i class="bi bi-tag-fill me-1" aria-hidden="true"></i>' + escape(data) + '</span>' + confidence
                        : '<span class="text-body-secondary">—</span>';
                },
            },
            {
                data: 'status',
                name: @js($itemsTable.'.status'),
                render: function (data, type, row) {
                    const allowedColors = ['secondary', 'info', 'primary', 'warning', 'danger', 'dark', 'success'];
                    const color = allowedColors.includes(row.status_class) ? row.status_class : 'secondary';
                    const reason = row.rejection_reason
                        ? '<div class="small text-danger item-status-reason mt-1">' + escape(row.rejection_reason) + '</div>'
                        : '';

                    return type === 'display'
                        ? '<span class="badge text-bg-' + color + '"><i class="bi bi-' + escape(statusIcons[data] || 'circle-fill') + ' me-1" aria-hidden="true"></i>'
                            + escape(statusLabels[data] || data) + '</span>' + reason
                        : data;
                },
            },
            {data: 'actions', name: 'actions', orderable: false, searchable: false},
        ],
    });

    document.getElementById('items-filter').addEventListener('submit', function (event) {
        event.preventDefault();
        table.ajax.reload();
    });
});
</script>
@endpush
