@can('operate-pipeline')
<form method="post" action="{{ route('admin.items.retry', $item) }}">
    @csrf
    <button class="btn btn-sm btn-primary" type="submit">Повторить</button>
</form>
@endcan
