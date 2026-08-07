@extends('admin.layout')

@section('title', 'Администраторы')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item active" aria-current="page">Администраторы</li>
@endsection

@section('content')
<div class="card card-primary card-outline shadow-sm administrators-card">
    <div class="card-header">
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
            <div>
                <h3 class="card-title mb-1">
                    <i class="bi bi-people-fill me-2 text-primary" aria-hidden="true"></i>
                    Пользователи административной панели
                </h3>
                <p class="mb-0 small text-body-secondary">
                    Управление доступом администраторов к настройкам и мониторингу.
                </p>
            </div>
            <div class="card-tools ms-md-auto">
                <a class="btn btn-primary" href="{{ route('admin.administrators.create') }}">
                    <i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>
                    Добавить администратора
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle administrator-table mb-0" id="administrators-table">
            <thead class="table-light">
                <tr>
                    <th>Логин</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Создан</th>
                    <th>Действия</th>
                </tr>
            </thead>
        </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .administrators-card .card-header {
        padding: 1rem 1.25rem;
    }
    .administrators-card .card-title {
        float: none;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .administrators-card .administrator-table {
        min-width: 820px;
        table-layout: fixed;
    }
    .administrators-card .administrator-table th:nth-child(1) { width: 32%; }
    .administrators-card .administrator-table th:nth-child(2) { width: 20%; }
    .administrators-card .administrator-table th:nth-child(3) { width: 16%; }
    .administrators-card .administrator-table th:nth-child(4) { width: 20%; }
    .administrators-card .administrator-table th:nth-child(5) { width: 12%; }
    .administrator-login {
        overflow-wrap: anywhere;
    }
    .administrator-avatar {
        align-items: center;
        background: var(--bs-primary-bg-subtle);
        border: 1px solid var(--bs-primary-border-subtle);
        border-radius: 50%;
        color: var(--bs-primary-text-emphasis);
        display: inline-flex;
        height: 2.25rem;
        justify-content: center;
        width: 2.25rem;
    }
    @media (max-width: 767.98px) {
        .administrators-card .card-tools,
        .administrators-card .card-tools .btn {
            width: 100%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const escape = window.AdminDataTables.escapeHtml;
    const currentUserId = @js(auth()->id());

    window.AdminDataTables.create('#administrators-table', {
        ajax: @js(route('admin.datatables.administrators')),
        order: [[0, 'asc']],
        columns: [
            {
                data: 'login',
                name: 'login',
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<div class="d-flex align-items-center gap-2"><span class="administrator-avatar flex-shrink-0"><i class="bi bi-person-fill" aria-hidden="true"></i></span>'
                            + '<div class="administrator-login"><div class="fw-semibold">' + escape(data) + '</div>'
                            + (Number(row.id) === Number(currentUserId) ? '<span class="badge rounded-pill text-bg-info">Текущая учётная запись</span>' : '')
                            + '</div></div>'
                        : data;
                },
            },
            {
                data: 'role',
                name: 'role',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle"><i class="bi bi-shield-lock-fill me-1" aria-hidden="true"></i>Администратор</span>'
                        : data;
                },
            },
            {
                data: 'is_active',
                name: 'is_active',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge text-bg-' + (data ? 'success' : 'secondary') + '"><i class="bi bi-' + (data ? 'check-circle-fill' : 'pause-circle-fill') + ' me-1" aria-hidden="true"></i>' + (data ? 'Активен' : 'Отключён') + '</span>'
                        : data;
                },
            },
            {
                data: 'created_at',
                name: 'created_at',
                render: function (data, type) {
                    if (type !== 'display' || !data) {
                        return data || '—';
                    }

                    const formatted = new Intl.DateTimeFormat('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    }).format(new Date(data));

                    return '<span class="text-nowrap"><i class="bi bi-calendar-plus me-1 text-body-secondary" aria-hidden="true"></i>' + escape(formatted) + '</span>';
                },
            },
            {data: 'actions', name: 'actions', orderable: false, searchable: false},
        ],
    });
});
</script>
@endpush
