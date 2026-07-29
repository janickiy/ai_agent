@can('manage-sources')
<div class="d-flex flex-wrap gap-1">
    <a class="btn btn-sm btn-primary" href="{{ route('admin.sources.edit', $source) }}" title="Редактировать">
        <i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span>
    </a>
    <form method="post" action="{{ route('admin.sources.toggle', $source) }}">
        @csrf
        @method('PATCH')
        <button class="btn btn-sm btn-secondary" type="submit">{{ $source->is_active ? 'Отключить' : 'Включить' }}</button>
    </form>
    <form method="post" action="{{ route('admin.sources.destroy', $source) }}" onsubmit="return confirm('Удалить источник? Это действие нельзя отменить.')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-danger" type="submit" title="Удалить">
            <i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span>
        </button>
    </form>
</div>
@endcan
