@extends('admin.layout')

@section('title', 'Администраторы')

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <a class="btn btn-primary btn-sm" href="{{ route('admin.administrators.create') }}"><i class="bi bi-plus-lg"></i> Добавить администратора</a>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0" id="administrators-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Имя</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Создан</th>
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
    const currentUserId = @js(auth()->id());

    window.AdminDataTables.create('#administrators-table', {
        ajax: @js(route('admin.datatables.administrators')),
        order: [[0, 'asc']],
        columns: [
            {data: 'email', name: 'email'},
            {
                data: 'name',
                name: 'name',
                render: function (data, type, row) {
                    return type === 'display'
                        ? '<strong>' + escape(data) + '</strong>' + (Number(row.id) === Number(currentUserId) ? '<span class="badge text-bg-info ms-1">вы</span>' : '')
                        : data;
                },
            },
            {
                data: 'role',
                name: 'role',
                render: function (data, type) {
                    return type === 'display' ? 'Администратор' : data;
                },
            },
            {
                data: 'is_active',
                name: 'is_active',
                render: function (data, type) {
                    return type === 'display'
                        ? '<span class="badge text-bg-' + (data ? 'success' : 'secondary') + '">' + (data ? 'активен' : 'отключён') + '</span>'
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

                    return new Intl.DateTimeFormat('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    }).format(new Date(data));
                },
            },
            {data: 'actions', name: 'actions', orderable: false, searchable: false},
        ],
    });
});
</script>
@endpush
