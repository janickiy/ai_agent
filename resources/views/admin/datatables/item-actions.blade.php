@can('operate-pipeline')
<div class="d-flex align-items-center gap-2 item-actions">
<form class="m-0" method="post" action="{{ route('admin.items.retry', $item) }}">
    @csrf
    <button class="btn btn-sm btn-outline-primary rounded" type="submit" title="Повторить обработку">
        <i class="bi bi-arrow-clockwise"></i>
        <span class="visually-hidden">Повторить обработку</span>
    </button>
</form>
@if($item->isAwaitingManualPublication())
<form class="m-0" method="post" action="{{ route('admin.items.publish', $item) }}">
    @csrf
    <button class="btn btn-sm btn-success rounded text-nowrap" type="submit" title="Опубликовать в Kaboom">
        <i class="bi bi-send-check me-1" aria-hidden="true"></i>Опубликовать
    </button>
</form>
@endif
</div>
@endcan
