@extends('admin.layout')

@section('title', 'Исходные публикации')

@section('content')
@php($itemsTable = \App\NewsMonitor\Support\NewsTables::name('source_items'))
<form class="row g-2 mb-3" id="items-filter">
    <div class="col-auto">
        <label class="visually-hidden" for="item-status">Статус</label>
        <select class="form-select" id="item-status">
            <option value="">Все статусы</option>
            @foreach(['discovered','fetched','extracted','analyzed','rejected_irrelevant','rejected_advertising','duplicate','validation_failed','accepted'] as $status)
            <option value="{{ $status }}">{{ $status }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Применить</button></div>
</form>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0" id="items-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Дата</th>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const escape = window.AdminDataTables.escapeHtml;
    const safeUrl = window.AdminDataTables.safeUrl;
    const status = document.getElementById('item-status');
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
            {data: 'id', name: @js($itemsTable.'.id')},
            {data: 'source_published_at', name: @js($itemsTable.'.source_published_at')},
            {data: 'source_name', name: 'source_table.name'},
            {
                data: 'title_original',
                name: 'title_original',
                render: function (data, type, row) {
                    const title = data || row.canonical_url;

                    return type === 'display'
                        ? '<a href="' + escape(safeUrl(row.canonical_url)) + '" target="_blank" rel="noopener noreferrer">' + escape(title) + '</a>'
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
                        : ' <small>(' + Number(row.category_confidence).toFixed(2) + ')</small>';

                    return escape(data || '—') + confidence;
                },
            },
            {
                data: 'status',
                name: @js($itemsTable.'.status'),
                render: function (data, type, row) {
                    const allowedColors = ['secondary', 'info', 'primary', 'warning', 'danger', 'dark', 'success'];
                    const color = allowedColors.includes(row.status_class) ? row.status_class : 'secondary';

                    return type === 'display'
                        ? '<span class="badge text-bg-' + color + '">' + escape(data) + '</span><br><small>' + escape(row.rejection_reason || '') + '</small>'
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
