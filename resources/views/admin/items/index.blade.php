@extends('admin.layout')

@section('title', 'Исходные публикации')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item active" aria-current="page">Материалы</li>
@endsection

@section('content')
@php
    $itemsTable = \App\Modules\NewsMonitor\Support\NewsTables::name('source_items');
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
    $itemReasonLabels = [
        'publication_output_disabled' => 'Ожидает ручной публикации',
        'kaboom_publication_queued' => 'Ожидает отправки в Kaboom',
        'kaboom_publication_failed' => 'Ошибка отправки в Kaboom',
    ];
@endphp

<div class="card card-primary card-outline shadow-sm admin-table-card items-card">
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
                    <th class="text-center">
                        @can('operate-pipeline')
                        <input class="form-check-input items-select-all" type="checkbox" aria-label="Отметить все материалы на странице">
                        @endcan
                    </th>
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
    @can('operate-pipeline')
    <div class="card-footer bg-body-tertiary">
        <form class="d-flex flex-wrap align-items-center justify-content-between gap-3" id="items-publish-form" method="post" action="{{ route('admin.items.publish-many') }}">
            @csrf
            <div>
                <div class="fw-semibold">Массовая ручная публикация</div>
                <div class="small text-body-secondary" id="items-selection-summary">Материалы не выбраны.</div>
            </div>
            <div id="items-publish-inputs"></div>
            <button class="btn btn-success" id="items-publish-button" type="submit" disabled>
                <i class="bi bi-send-check me-1" aria-hidden="true"></i>
                Опубликовать
                <span class="badge text-bg-light ms-1" id="items-publish-count">0</span>
            </button>
        </form>
    </div>
    @endcan
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
        min-width: 1650px;
        table-layout: fixed;
    }
    .items-card .item-table th:nth-child(1) { width: 3%; }
    .items-card .item-table th:nth-child(2) { width: 4%; }
    .items-card .item-table th:nth-child(3) { width: 11%; }
    .items-card .item-table th:nth-child(4) { width: 10.5%; }
    .items-card .item-table th:nth-child(5) { width: 10.5%; }
    .items-card .item-table th:nth-child(6) { width: 9%; }
    .items-card .item-table th:nth-child(7) { width: 18%; }
    .items-card .item-table th:nth-child(8) { width: 10%; }
    .items-card .item-table th:nth-child(9) { width: 12%; }
    .items-card .item-table th:nth-child(10) { width: 12%; }
    .items-card table.dataTable > thead > tr > th {
        font-size: .9rem;
        padding: .85rem .75rem;
        vertical-align: middle;
    }
    .items-card table.dataTable > thead > tr > th .dt-column-header {
        align-items: center;
        gap: .75rem;
        min-height: 1.5rem;
    }
    .items-card table.dataTable > thead > tr > th .dt-column-order {
        flex: 0 0 .8rem;
    }
    .items-card table.dataTable > thead > tr > th:nth-child(2) .dt-column-header {
        flex-direction: row;
    }
    .items-card table.dataTable > thead > tr > th:nth-child(2),
    .items-card table.dataTable > tbody > tr > td:nth-child(2),
    .items-card table.dataTable > thead > tr > th:nth-child(10) {
        text-align: center;
    }
    .items-card table.dataTable > tbody > tr > td {
        padding-inline: .65rem;
    }
    .item-id {
        min-width: 2.5rem;
    }
    .item-date {
        align-items: center;
        display: flex;
        gap: .4rem;
        line-height: 1.25;
        max-width: 100%;
        min-width: 0;
        white-space: normal;
        width: 100%;
    }
    .item-date-value {
        min-width: 0;
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
    .item-actions {
        min-width: 10rem;
    }
    .item-publish-checkbox {
        cursor: pointer;
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
    const itemsCard = document.querySelector('.items-card');
    const status = document.getElementById('item-status');
    const publishForm = document.getElementById('items-publish-form');
    const publishButton = document.getElementById('items-publish-button');
    const publishCount = document.getElementById('items-publish-count');
    const publishInputs = document.getElementById('items-publish-inputs');
    const selectionSummary = document.getElementById('items-selection-summary');
    const selectedItemIds = new Set();
    const statusLabels = @js($itemStatusLabels);
    const statusIcons = @js($itemStatusIcons);
    const reasonLabels = @js($itemReasonLabels);

    function renderDate(data, type, icon) {
        if (type !== 'display') {
            return data || '';
        }

        return data
            ? '<span class="item-date"><i class="bi bi-' + icon + ' text-body-secondary" aria-hidden="true"></i><span class="item-date-value">' + escape(data) + '</span></span>'
            : '<span class="text-body-secondary">—</span>';
    }

    const table = window.AdminDataTables.create('#items-table', {
        ajax: {
            url: @js(route('admin.datatables.items')),
            data: function (request) {
                request.status = status.value;
            },
        },
        order: [[2, 'desc']],
        scrollX: true,
        columns: [
            {
                data: 'id',
                name: 'selection',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    if (type !== 'display' || !row.manual_publication_available) {
                        return '';
                    }

                    const id = String(data);
                    const checked = selectedItemIds.has(id) ? ' checked' : '';

                    return '<input class="form-check-input item-publish-checkbox" type="checkbox" value="'
                        + escape(id) + '" aria-label="Выбрать материал #' + escape(id) + '"' + checked + '>';
                },
            },
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
                    const reasonClass = row.rejection_reason === 'kaboom_publication_queued' ? 'text-info-emphasis' : 'text-danger';
                    const reason = row.rejection_reason
                        ? '<div class="small ' + reasonClass + ' item-status-reason mt-1">' + escape(reasonLabels[row.rejection_reason] || row.rejection_reason) + '</div>'
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

    function currentPublishableIds() {
        return table.rows({page: 'current'}).data().toArray()
            .filter(function (row) {
                return Boolean(row.manual_publication_available);
            })
            .map(function (row) {
                return String(row.id);
            });
    }

    function updateSelectionControls() {
        const count = selectedItemIds.size;

        if (publishButton) {
            publishButton.disabled = count === 0;
        }
        if (publishCount) {
            publishCount.textContent = String(count);
        }
        if (selectionSummary) {
            selectionSummary.textContent = count === 0
                ? 'Материалы не выбраны.'
                : 'Выбрано материалов: ' + count + '.';
        }

        const selectAllCheckboxes = document.querySelectorAll('.items-select-all');

        if (selectAllCheckboxes.length === 0) {
            return;
        }

        const pageIds = currentPublishableIds();
        const selectedOnPage = pageIds.filter(function (id) {
            return selectedItemIds.has(id);
        }).length;

        selectAllCheckboxes.forEach(function (checkbox) {
            checkbox.disabled = pageIds.length === 0;
            checkbox.checked = pageIds.length > 0 && selectedOnPage === pageIds.length;
            checkbox.indeterminate = selectedOnPage > 0 && selectedOnPage < pageIds.length;
        });
    }

    itemsCard.addEventListener('change', function (event) {
        const rowCheckbox = event.target.closest('.item-publish-checkbox');

        if (rowCheckbox) {
            if (rowCheckbox.checked) {
                selectedItemIds.add(rowCheckbox.value);
            } else {
                selectedItemIds.delete(rowCheckbox.value);
            }

            updateSelectionControls();

            return;
        }

        const selectAllCheckbox = event.target.closest('.items-select-all');

        if (!selectAllCheckbox) {
            return;
        }

        if (selectAllCheckbox.checked) {
            currentPublishableIds().forEach(function (id) {
                selectedItemIds.add(id);
            });
        } else {
            selectedItemIds.clear();
        }

        document.querySelectorAll('.item-publish-checkbox').forEach(function (checkbox) {
            checkbox.checked = selectedItemIds.has(checkbox.value);
        });
        updateSelectionControls();
    });

    table.on('draw', function () {
        document.querySelectorAll('.item-publish-checkbox').forEach(function (checkbox) {
            checkbox.checked = selectedItemIds.has(checkbox.value);
        });
        updateSelectionControls();
    });

    publishForm?.addEventListener('submit', function (event) {
        publishInputs.replaceChildren();

        if (selectedItemIds.size === 0) {
            event.preventDefault();

            return;
        }

        selectedItemIds.forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'item_ids[]';
            input.value = id;
            publishInputs.appendChild(input);
        });
        publishButton.disabled = true;
    });

    document.getElementById('items-filter').addEventListener('submit', function (event) {
        event.preventDefault();
        selectedItemIds.clear();
        updateSelectionControls();
        table.ajax.reload();
    });
});
</script>
@endpush
