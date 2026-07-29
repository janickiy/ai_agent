<div class="d-flex flex-nowrap gap-1">
    <a class="btn btn-sm btn-primary" href="{{ route('admin.administrators.edit', $administrator) }}" title="Редактировать">
        <i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span>
    </a>
    @unless(auth()->user()->is($administrator))
    <form method="post" action="{{ route('admin.administrators.destroy', $administrator) }}" onsubmit="return confirm('Удалить администратора? Это действие нельзя отменить.')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-danger" type="submit" title="Удалить">
            <i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span>
        </button>
    </form>
    @endunless
</div>
