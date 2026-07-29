@extends('admin.layout')

@section('title', 'Готовые посты')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item active" aria-current="page">Готовые посты</li>
@endsection

@section('content')
@php($postsTable = \App\NewsMonitor\Support\NewsTables::name('posts'))
<div class="card card-primary card-outline shadow-sm posts-card">
    <div class="card-header">
        <div>
            <h3 class="card-title mb-1">
                <i class="bi bi-send-check-fill me-2 text-primary" aria-hidden="true"></i>
                Публикации, подготовленные ИИ-агентом
            </h3>
            <p class="mb-0 small text-body-secondary">
                Проверенные материалы с описанием, рубрикой и набором хэштегов.
            </p>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered table-hover align-middle post-table mb-0" id="posts-table">
            <thead class="table-light">
                <tr>
                    <th>Изображение</th>
                    <th>Публикация</th>
                    <th>Источник</th>
                    <th>Категория</th>
                    <th>Хэштеги</th>
                    <th>Статус</th>
                    <th>Добавлено</th>
                    <th>Обновлено</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('styles')
<style>
    .posts-card .card-header {
        padding: 1rem 1.25rem;
    }
    .posts-card .card-title {
        float: none;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .posts-card .post-table {
        min-width: 1500px;
        table-layout: fixed;
    }
    .posts-card .post-table th:nth-child(1) { width: 9%; }
    .posts-card .post-table th:nth-child(2) { width: 29%; }
    .posts-card .post-table th:nth-child(3) { width: 15%; }
    .posts-card .post-table th:nth-child(4) { width: 10%; }
    .posts-card .post-table th:nth-child(5) { width: 11%; }
    .posts-card .post-table th:nth-child(6) { width: 9%; }
    .posts-card .post-table th:nth-child(7) { width: 8.5%; }
    .posts-card .post-table th:nth-child(8) { width: 8.5%; }
    .posts-card table.dataTable > thead > tr > th {
        font-size: .9rem;
        padding-inline: .65rem;
    }
    .posts-card table.dataTable > tbody > tr > td {
        padding-inline: .65rem;
    }
    .post-image {
        aspect-ratio: 3 / 2;
        border-radius: .5rem;
        box-shadow: var(--bs-box-shadow-sm);
        display: block;
        height: auto;
        object-fit: cover;
        width: 100%;
    }
    .post-image-placeholder {
        align-items: center;
        aspect-ratio: 3 / 2;
        background: var(--bs-secondary-bg);
        border: 1px dashed var(--bs-border-color);
        border-radius: .5rem;
        color: var(--bs-secondary-color);
        display: flex;
        font-size: 1.5rem;
        justify-content: center;
        width: 100%;
    }
    .post-title {
        display: block;
        font-weight: 600;
        line-height: 1.35;
        text-decoration: none;
    }
    .post-description {
        display: -webkit-box;
        font-size: .78rem;
        line-height: 1.35;
        margin-top: .35rem;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
    }
    .post-source,
    .post-category,
    .post-hashtag {
        font-size: .75rem;
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .post-date {
        align-items: flex-start;
        display: inline-flex;
        gap: .4rem;
        max-width: 100%;
    }
    .post-date-value {
        display: flex;
        flex-direction: column;
        line-height: 1.25;
        min-width: 0;
    }
    .post-date-day,
    .post-date-time {
        white-space: nowrap;
    }
    .post-date-time {
        color: var(--bs-secondary-color);
        font-size: .78rem;
        margin-top: .15rem;
    }
    .posts-card .dt-scroll-body {
        border-bottom: 1px solid var(--bs-border-color);
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const escape = window.AdminDataTables.escapeHtml;
    const safeUrl = window.AdminDataTables.safeUrl;
    const postStatuses = {
        ready: {label: 'Готов', color: 'success', icon: 'check-circle-fill'},
        reserved: {label: 'Зарезервирован', color: 'info', icon: 'bookmark-check-fill'},
        exported: {label: 'Опубликован', color: 'primary', icon: 'send-check-fill'},
        export_failed: {label: 'Ошибка публикации', color: 'danger', icon: 'exclamation-triangle-fill'},
        disabled: {label: 'Отключён', color: 'secondary', icon: 'pause-circle-fill'},
    };

    function renderPostDate(data, type, icon) {
        if (type !== 'display') {
            return data || '';
        }

        if (!data) {
            return '<span class="text-body-secondary">—</span>';
        }

        const value = String(data);
        const separator = value.lastIndexOf(' ');
        const day = separator === -1 ? value : value.slice(0, separator);
        const time = separator === -1 ? '' : value.slice(separator + 1);

        return '<span class="post-date">'
            + '<i class="bi bi-' + icon + ' text-body-secondary mt-1" aria-hidden="true"></i>'
            + '<span class="post-date-value"><span class="post-date-day">' + escape(day) + '</span>'
            + (time ? '<span class="post-date-time">' + escape(time) + '</span>' : '')
            + '</span></span>';
    }

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
                        ? '<img class="post-image" src="' + escape(safeUrl(data)) + '" alt="" loading="lazy" referrerpolicy="no-referrer">'
                        : '<span class="post-image-placeholder" title="Изображение отсутствует"><i class="bi bi-image" aria-hidden="true"></i></span>';
                },
            },
            {
                data: 'title_original',
                name: 'title_original',
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<a class="post-title" href="' + escape(safeUrl(row.source_url)) + '" target="_blank" rel="noopener noreferrer">'
                            + escape(data) + '<i class="bi bi-box-arrow-up-right ms-1 small" aria-hidden="true"></i></a>'
                            + '<div class="post-description text-body-secondary">' + escape(row.description_original || '') + '</div>'
                        : data;
                },
            },
            {
                data: 'source_published_at',
                name: @js($postsTable.'.source_published_at'),
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<span class="badge text-bg-light border post-source"><i class="bi bi-rss me-1 text-primary" aria-hidden="true"></i>' + escape(row.source_name) + '</span>'
                            + '<div class="mt-2"><a class="small text-decoration-none" href="' + escape(safeUrl(row.source_url)) + '" target="_blank" rel="noopener noreferrer">'
                            + escape(row.read_more_label) + '<i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a></div>'
                            + '<div class="small text-body-secondary mt-1"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>' + escape(data || '—') + '</div>'
                        : data;
                },
            },
            {
                data: 'category_name',
                name: 'category_table.name',
                defaultContent: '—',
                render: function (data, type) {
                    return type === 'display' && data
                        ? '<span class="badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle post-category">'
                            + '<i class="bi bi-tag-fill me-1" aria-hidden="true"></i>' + escape(data) + '</span>'
                        : (data || '—');
                },
            },
            {
                data: 'hashtags',
                name: @js($postsTable.'.hashtags'),
                orderable: false,
                searchable: false,
                render: function (data, type) {
                    if (type !== 'display') {
                        return data || '';
                    }

                    const hashtags = String(data || '').split(/\s+/).filter(Boolean);

                    return hashtags.length
                        ? hashtags.map(function (hashtag) {
                            return '<span class="badge text-bg-light border post-hashtag me-1 mb-1">' + escape(hashtag) + '</span>';
                        }).join('')
                        : '<span class="text-body-secondary">—</span>';
                },
            },
            {
                data: 'status',
                name: @js($postsTable.'.status'),
                render: function (data, type) {
                    const status = postStatuses[data] || {label: data, color: 'secondary', icon: 'circle-fill'};

                    return type === 'display'
                        ? '<span class="badge text-bg-' + status.color + '"><i class="bi bi-' + status.icon + ' me-1" aria-hidden="true"></i>' + escape(status.label) + '</span>'
                        : data;
                },
            },
            {
                data: 'created_at',
                name: @js($postsTable.'.created_at'),
                render: function (data, type) {
                    return renderPostDate(data, type, 'plus-circle');
                },
            },
            {
                data: 'updated_at',
                name: @js($postsTable.'.updated_at'),
                render: function (data, type) {
                    return renderPostDate(data, type, 'arrow-repeat');
                },
            },
        ],
    });
});
</script>
@endpush
