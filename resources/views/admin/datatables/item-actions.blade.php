@can('operate-pipeline')
<form class="m-0" method="post" action="{{ route('admin.items.retry', $item) }}">
    @csrf
    <button class="btn btn-sm btn-outline-primary rounded" type="submit" title="Повторить обработку">
        <i class="bi bi-arrow-clockwise"></i>
        <span class="visually-hidden">Повторить обработку</span>
    </button>
</form>
@endcan
