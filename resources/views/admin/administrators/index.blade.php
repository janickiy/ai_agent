@extends('admin.layout')

@section('title', 'Администраторы')

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <a class="btn btn-primary btn-sm" href="{{ route('admin.administrators.create') }}"><i class="bi bi-plus-lg"></i> Добавить администратора</a>
        <form class="d-flex ms-auto" method="get" action="{{ route('admin.administrators.index') }}" role="search">
            <div class="input-group input-group-sm">
                <input class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Имя или email" aria-label="Поиск администратора">
                <button class="btn btn-outline-secondary" type="submit" title="Найти"><i class="bi bi-search"></i><span class="visually-hidden">Найти</span></button>
            </div>
        </form>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Имя</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Создан</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($administrators as $administrator)
                <tr>
                    <td>{{ $administrator->email }}</td>
                    <td>
                        <strong>{{ $administrator->name }}</strong>
                        @if(auth()->user()->is($administrator))<span class="badge text-bg-info ms-1">вы</span>@endif
                    </td>
                    <td>Администратор</td>
                    <td><span class="badge text-bg-{{ $administrator->is_active ? 'success' : 'secondary' }}">{{ $administrator->is_active ? 'активен' : 'отключён' }}</span></td>
                    <td>{{ $administrator->created_at?->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</td>
                    <td>
                        <div class="d-flex flex-nowrap gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.administrators.edit', $administrator) }}" title="Редактировать">
                                <i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span>
                            </a>
                            @unless(auth()->user()->is($administrator))
                            <form method="post" action="{{ route('admin.administrators.destroy', $administrator) }}" onsubmit="return confirm('Удалить администратора «{{ addslashes($administrator->name) }}»? Это действие нельзя отменить.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Удалить">
                                    <i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span>
                                </button>
                            </form>
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4">Администраторы не найдены.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($administrators->hasPages())<div class="card-footer">{{ $administrators->links() }}</div>@endif
</div>
@endsection
