@can('manage-categories')
<div class="d-flex flex-nowrap gap-1">
    <a class="btn btn-sm btn-primary" href="{{ route('admin.categories.edit', $category) }}" title="Редактировать">
        <i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span>
    </a>
    <form method="post" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Удалить тематику? Это действие нельзя отменить.')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-danger" type="submit" title="Удалить">
            <i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span>
        </button>
    </form>
</div>
@endcan
