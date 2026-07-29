@extends('admin.layout')

@section('title', 'Открытые источники')

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong>Источники мониторинга</strong>
        @can('manage-sources')
        <a class="btn btn-primary btn-sm" href="{{ route('admin.sources.create') }}"><i class="bi bi-plus-lg"></i> Добавить источник</a>
        @endcan
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0" id="sources-table">
            <thead>
                <tr>
                    <th>Источник</th>
                    <th>Тип</th>
                    <th>Доверие</th>
                    <th>Лента</th>
                    <th>Период</th>
                    <th>Материалов</th>
                    <th>Статус</th>
                    <th>Действия</th>
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
    const safeUrl = window.AdminDataTables.safeUrl;

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
                        ? '<strong>' + escape(data) + '</strong><br><a class="text-secondary text-decoration-none" href="' + escape(safeUrl(row.base_url)) + '" target="_blank" rel="noopener noreferrer"><small>' + escape(row.domain) + '</small></a>'
                        : data;
                },
            },
            {data: 'source_class', name: 'source_class'},
            {
                data: 'trust_score',
                name: 'trust_score',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge text-bg-' + (Number(data) >= 85 ? 'success' : 'warning') + '">' + escape(data) + '</span>'
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
                        ? '<a href="' + escape(safeUrl(row.feed_url)) + '" target="_blank" rel="noopener noreferrer">' + escape(String(data).toUpperCase()) + '</a>'
                        : '<span class="text-secondary">не указана</span>';
                },
            },
            {
                data: 'poll_interval_minutes',
                name: 'poll_interval_minutes',
                render: function (data, type) {
                    return type === 'display' ? escape(data) + ' мин.' : data;
                },
            },
            {data: 'items_count', name: 'items_count', searchable: false},
            {
                data: 'is_active',
                name: 'is_active',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }

                    const color = row.status === 'error' ? 'danger' : (data ? 'success' : 'secondary');
                    const catalogOnly = row.feed_url === null ? '<br><small class="text-secondary">только каталог</small>' : '';

                    return '<span class="badge text-bg-' + color + '">' + (data ? 'active' : 'отключен') + '</span>' + catalogOnly;
                },
            },
            {data: 'actions', name: 'actions', orderable: false, searchable: false},
        ],
    });
});
</script>
@endpush
