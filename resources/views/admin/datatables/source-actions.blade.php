@can('manage-sources')
<div class="d-flex flex-nowrap align-items-center gap-2" role="group" aria-label="Действия с источником {{ $source->name }}">
    <a class="btn btn-sm btn-outline-primary rounded" href="{{ route('admin.sources.edit', $source) }}" title="Редактировать">
        <i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span>
    </a>
    <form class="m-0" method="post" action="{{ route('admin.sources.toggle', $source) }}">
        @csrf
        @method('PATCH')
        <button class="btn btn-sm btn-outline-{{ $source->is_active ? 'warning' : 'success' }} rounded" type="submit" title="{{ $source->is_active ? 'Отключить' : 'Включить' }}">
            <i class="bi bi-{{ $source->is_active ? 'pause' : 'play' }}"></i>
            <span class="visually-hidden">{{ $source->is_active ? 'Отключить' : 'Включить' }}</span>
        </button>
    </form>
    <form class="m-0" method="post" action="{{ route('admin.sources.destroy', $source) }}" onsubmit="return confirm('Удалить источник? Это действие нельзя отменить.')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger rounded" type="submit" title="Удалить">
            <i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span>
        </button>
    </form>
</div>
@endcan
