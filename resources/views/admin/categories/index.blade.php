@extends('admin.layout')

@section('title', 'Тематики')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item active" aria-current="page">Тематики</li>
@endsection

@section('content')
<div class="card card-primary card-outline shadow-sm admin-table-card categories-card">
    <div class="card-header">
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
            <div>
                <h3 class="card-title mb-1">
                    <i class="bi bi-tags-fill me-2 text-primary" aria-hidden="true"></i>
                    Список тематик
                </h3>
                <p class="mb-0 small text-body-secondary">
                    Управление рубриками, ключевыми словами и привязкой источников.
                </p>
            </div>
        @can('manage-categories')
            <div class="card-tools ms-md-auto">
                <a class="btn btn-primary" href="{{ route('admin.categories.create') }}">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
                    Добавить тематику
                </a>
            </div>
        @endcan
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle category-table mb-0" id="categories-table">
                <thead class="table-light">
                    <tr>
                        <th>Тематика</th>
                        <th>Хэштег</th>
                        <th>Ключевые слова</th>
                        <th class="text-center">Источников</th>
                        <th>Статус</th>
                        <th class="text-center">Действия</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .categories-card {
        --category-icon-size: 2.25rem;
    }
    .categories-card .card-header {
        padding: 1rem 1.25rem;
    }
    .categories-card .card-title {
        float: none;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .categories-card .category-table {
        min-width: 960px;
        table-layout: fixed;
    }
    .categories-card .category-table th:nth-child(1) { width: 22%; }
    .categories-card .category-table th:nth-child(2) { width: 16%; }
    .categories-card .category-table th:nth-child(3) { width: 25%; }
    .categories-card .category-table th:nth-child(4) { width: 12%; }
    .categories-card .category-table th:nth-child(5) { width: 14%; }
    .categories-card .category-table th:nth-child(6) { width: 11%; }
    .categories-card .category-table th:last-child,
    .categories-card .category-table td:last-child {
        padding-right: .85rem !important;
    }
    .category-icon {
        align-items: center;
        background: var(--bs-primary-bg-subtle);
        border: 1px solid var(--bs-primary-border-subtle);
        border-radius: .5rem;
        color: var(--bs-primary-text-emphasis);
        display: inline-flex;
        height: var(--category-icon-size);
        justify-content: center;
        width: var(--category-icon-size);
    }
    .category-code {
        font-size: .72rem;
        font-weight: 500;
    }
    .category-hashtag {
        font-size: .78rem;
        font-weight: 600;
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .category-source-count {
        min-width: 2.25rem;
    }
    @media (max-width: 767.98px) {
        .categories-card .card-tools,
        .categories-card .card-tools .btn {
            width: 100%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const escape = window.AdminDataTables.escapeHtml;

    window.AdminDataTables.create('#categories-table', {
        ajax: @js(route('admin.datatables.categories')),
        order: [[0, 'asc']],
        columns: [
            {
                data: 'name',
                name: 'name',
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<div class="d-flex align-items-center gap-2">'
                            + '<span class="category-icon flex-shrink-0"><i class="bi bi-tag-fill" aria-hidden="true"></i></span>'
                            + '<div class="min-w-0"><div class="fw-semibold">' + escape(data) + '</div>'
                            + '<span class="badge rounded-pill text-bg-light border font-monospace category-code">' + escape(row.code) + '</span></div>'
                            + '</div>'
                        : data;
                },
            },
            {
                data: 'hashtag',
                name: 'hashtag',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle category-hashtag">' + escape(data || '—') + '</span>'
                        : data;
                },
            },
            {
                data: 'keywords',
                name: 'keywords',
                orderable: false,
                searchable: false,
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="category-keywords text-body-secondary" title="' + escape(data || '') + '">' + escape(data || '—') + '</span>'
                        : data;
                },
            },
            {
                data: 'sources_count',
                name: 'sources_count',
                searchable: false,
                className: 'text-center',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge rounded-pill text-bg-info category-source-count">' + escape(data) + '</span>'
                        : data;
                },
            },
            {
                data: 'is_active',
                name: 'is_active',
                render: function (data, type) {
                    if (type !== 'display') {
                        return data;
                    }

                    return '<span class="badge text-bg-' + (data ? 'success' : 'secondary') + '">'
                        + '<i class="bi bi-' + (data ? 'check-circle-fill' : 'pause-circle-fill') + ' me-1" aria-hidden="true"></i>'
                        + (data ? 'Активна' : 'Неактивна') + '</span>';
                },
            },
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                searchable: false,
                className: 'text-center',
            },
        ],
    });
});
</script>
@endpush
