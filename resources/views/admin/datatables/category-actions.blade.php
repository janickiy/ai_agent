@can('manage-categories')
<div class="d-flex flex-nowrap align-items-center gap-2" role="group" aria-label="Действия с тематикой {{ $category->name }}">
    <a class="btn btn-sm btn-outline-primary rounded" href="{{ route('admin.categories.edit', $category) }}" title="Редактировать">
        <i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span>
    </a>
    <form class="m-0" method="post" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Удалить тематику? Это действие нельзя отменить.')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger rounded" type="submit" title="Удалить">
            <i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span>
        </button>
    </form>
</div>
@endcan
