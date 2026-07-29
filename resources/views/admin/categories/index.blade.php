@extends('admin.layout')

@section('title', 'Тематики')

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong>Список тематик</strong>
        @can('manage-categories')
        <a class="btn btn-primary btn-sm ms-auto" href="{{ route('admin.categories.create') }}"><i class="bi bi-plus-lg"></i> Добавить тематику</a>
        @endcan
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped category-table mb-0" id="categories-table">
            <thead>
                <tr>
                    <th>Тематика</th>
                    <th>Хэштег</th>
                    <th>Ключевые слова</th>
                    <th>Источников</th>
                    <th>Статус</th>
                    <th></th>
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

    window.AdminDataTables.create('#categories-table', {
        ajax: @js(route('admin.datatables.categories')),
        order: [[0, 'asc']],
        columns: [
            {
                data: 'name',
                name: 'name',
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<strong>' + escape(data) + '</strong><br><small><code>' + escape(row.code) + '</code></small>'
                        : data;
                },
            },
            {data: 'hashtag', name: 'hashtag'},
            {
                data: 'keywords',
                name: 'keywords',
                orderable: false,
                searchable: false,
                render: function (data, type) {
                    return type === 'display'
                        ? '<small class="category-keywords" title="' + escape(data) + '">' + escape(data) + '</small>'
                        : data;
                },
            },
            {data: 'sources_count', name: 'sources_count', searchable: false},
            {
                data: 'is_active',
                name: 'is_active',
                render: function (data, type) {
                    if (type !== 'display') {
                        return data;
                    }

                    return '<span class="badge text-bg-' + (data ? 'success' : 'secondary') + '">' + (data ? 'активна' : 'неактивна') + '</span>';
                },
            },
            {data: 'actions', name: 'actions', orderable: false, searchable: false},
        ],
    });
});
</script>
@endpush
