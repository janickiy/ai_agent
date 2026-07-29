@extends('admin.layout')

@section('title', 'Готовые посты')

@section('content')
@php($postsTable = \App\NewsMonitor\Support\NewsTables::name('posts'))
<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0" id="posts-table">
            <thead>
                <tr>
                    <th>Изображение</th>
                    <th>Публикация</th>
                    <th>Источник</th>
                    <th>Категория</th>
                    <th>Хэштеги</th>
                    <th>Статус</th>
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

    window.AdminDataTables.create('#posts-table', {
        ajax: @js(route('admin.datatables.posts')),
        order: [[2, 'desc']],
        scrollX: true,
        columns: [
            {
                data: 'image_url',
                name: @js($postsTable.'.image_url'),
                orderable: false,
                searchable: false,
                render: function (data, type) {
                    if (type !== 'display') {
                        return data || '';
                    }

                    return data
                        ? '<img src="' + escape(safeUrl(data)) + '" alt="" width="96" height="64" style="object-fit:cover" referrerpolicy="no-referrer">'
                        : '<span class="text-secondary">нет</span>';
                },
            },
            {
                data: 'title_original',
                name: 'title_original',
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<strong>' + escape(data) + '</strong><br><small>' + escape(row.description_original) + '</small>'
                        : data;
                },
            },
            {
                data: 'source_published_at',
                name: @js($postsTable.'.source_published_at'),
                render: function (data, type, row) {
                    return type === 'display'
                        ? escape(row.source_name) + '<br><a href="' + escape(safeUrl(row.source_url)) + '" target="_blank" rel="noopener noreferrer">' + escape(row.read_more_label) + '</a><br><small>' + escape(data) + '</small>'
                        : data;
                },
            },
            {data: 'category_name', name: 'category_table.name', defaultContent: '—'},
            {data: 'hashtags', name: @js($postsTable.'.hashtags'), orderable: false, searchable: false},
            {
                data: 'status',
                name: @js($postsTable.'.status'),
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge text-bg-success">' + escape(data) + '</span>'
                        : data;
                },
            },
        ],
    });
});
</script>
@endpush
