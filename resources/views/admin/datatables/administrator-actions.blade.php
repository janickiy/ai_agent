<div class="d-flex flex-nowrap align-items-center gap-2" role="group" aria-label="Действия с администратором {{ $administrator->name }}">
    <a class="btn btn-sm btn-outline-primary rounded" href="{{ route('admin.administrators.edit', $administrator) }}" title="Редактировать">
        <i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span>
    </a>
    @unless(auth()->user()->is($administrator))
    <form class="m-0" method="post" action="{{ route('admin.administrators.destroy', $administrator) }}" onsubmit="return confirm('Удалить администратора? Это действие нельзя отменить.')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger rounded" type="submit" title="Удалить">
            <i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span>
        </button>
    </form>
    @endunless
</div>
