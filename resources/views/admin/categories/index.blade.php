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
        <table class="table table-striped category-table mb-0">
            <colgroup>
                <col style="width: 24%">
                <col style="width: 20%">
                <col style="width: 32%">
                <col style="width: 9%">
                <col style="width: 9%">
                <col style="width: 72px">
            </colgroup>
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
            <tbody>
            @forelse($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong><br><small><code>{{ $category->code }}</code></small></td>
                    <td class="category-value">{{ $category->hashtag }}</td>
                    <td><small class="category-keywords" title="{{ implode(', ', $category->keywords ?? []) }}">{{ implode(', ', $category->keywords ?? []) }}</small></td>
                    <td>{{ $category->sources_count }}</td>
                    <td><span class="badge text-bg-{{ $category->is_active ? 'success' : 'secondary' }}">{{ $category->is_active ? 'активна' : 'неактивна' }}</span></td>
                    <td>
                        @can('manage-categories')
                        <div class="d-flex flex-nowrap gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.categories.edit', $category) }}" title="Редактировать">
                                <i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span>
                            </a>
                            <form method="post" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Удалить тематику «{{ addslashes($category->name) }}»? Это действие нельзя отменить.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Удалить">
                                    <i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span>
                                </button>
                            </form>
                        </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4">Тематики ещё не добавлены.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($categories->hasPages())<div class="card-footer">{{ $categories->links() }}</div>@endif
</div>
@endsection
