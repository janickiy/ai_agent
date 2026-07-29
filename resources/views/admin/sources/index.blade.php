@extends('admin.layout')

@section('title', 'Открытые источники')

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong>Источники мониторинга</strong>
        @can('manage-sources')
        <a class="btn btn-primary btn-sm" href="{{ route('admin.sources.create') }}"><i class="bi bi-plus-lg"></i> Добавить источник</a>
        @endcan
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>Источник</th><th>Тип</th><th>Доверие</th><th>Лента</th><th>Период</th><th>Материалов</th><th>Статус</th><th></th></tr></thead>
            <tbody>
            @forelse($sources as $source)
                <tr>
                    <td><strong>{{ $source->name }}</strong><br><a class="text-secondary text-decoration-none" href="{{ $source->base_url }}" target="_blank" rel="noopener noreferrer"><small>{{ $source->domain }}</small></a></td>
                    <td><span title="{{ $sourceClasses[$source->source_class] ?? $source->source_class }}">{{ $source->source_class }}</span></td>
                    <td><span class="badge text-bg-{{ $source->trust_score >= 85 ? 'success' : 'warning' }}">{{ $source->trust_score }}</span></td>
                    <td class="source-link">@if($source->feed_url)<a href="{{ $source->feed_url }}" target="_blank" rel="noopener noreferrer">{{ strtoupper($source->type) }}</a>@else<span class="text-secondary">не указана</span>@endif</td>
                    <td>{{ $source->poll_interval_minutes }} мин.</td>
                    <td>{{ $source->items_count }}</td>
                    <td>
                        <span class="badge text-bg-{{ $source->status === 'error' ? 'danger' : ($source->is_active ? 'success' : 'secondary') }}">{{ $source->is_active ? 'active' : 'отключен' }}</span>
                        @if($source->feed_url === null)<br><small class="text-secondary">только каталог</small>@endif
                    </td>
                    <td>
                        @can('manage-sources')
                        <div class="d-flex flex-wrap gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.sources.edit', $source) }}" title="Редактировать"><i class="bi bi-pencil"></i><span class="visually-hidden">Редактировать</span></a>
                            <form method="post" action="{{ route('admin.sources.toggle', $source) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $source->is_active ? 'Отключить' : 'Включить' }}</button></form>
                            <form method="post" action="{{ route('admin.sources.destroy', $source) }}" onsubmit="return confirm('Удалить источник «{{ addslashes($source->name) }}»? Это действие нельзя отменить.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Удалить"><i class="bi bi-trash"></i><span class="visually-hidden">Удалить</span></button>
                            </form>
                        </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4">Источники ещё не добавлены. Белый список согласуется перед промышленным запуском.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($sources->hasPages())<div class="card-footer">{{ $sources->links() }}</div>@endif
</div>
@endsection
