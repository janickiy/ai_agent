@extends('admin.layout')

@section('title', 'Исходные публикации')

@section('content')
<form class="row g-2 mb-3">
    <div class="col-auto"><select class="form-select" name="status"><option value="">Все статусы</option>@foreach(['discovered','fetched','extracted','analyzed','rejected_irrelevant','rejected_advertising','duplicate','validation_failed','accepted'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-outline-primary">Применить</button></div>
</form>
<div class="card"><div class="card-body table-responsive p-0">
    <table class="table table-striped mb-0"><thead><tr><th>ID</th><th>Дата</th><th>Источник</th><th>Заголовок</th><th>Категория</th><th>Статус / причина</th><th></th></tr></thead><tbody>
    @forelse($items as $item)
    <tr>
        <td>{{ $item->id }}</td>
        <td>{{ $item->source_published_at?->timezone(config('app.display_timezone'))->format('d.m.Y H:i') ?? '—' }}</td>
        <td>{{ $item->source->name }}</td>
        <td><a href="{{ $item->canonical_url }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($item->title_original ?: $item->canonical_url, 90) }}</a></td>
        <td>{{ $item->analysis?->category?->name ?? '—' }} @if($item->analysis)<small>({{ number_format($item->analysis->category_confidence, 2) }})</small>@endif</td>
        <td><span class="badge text-bg-secondary">{{ $item->status }}</span><br><small>{{ $item->rejection_reason }}</small></td>
        <td>@can('operate-pipeline')<form method="post" action="{{ route('admin.items.retry', $item) }}">@csrf<button class="btn btn-sm btn-outline-primary">Повторить</button></form>@endcan</td>
    </tr>
    @empty<tr><td colspan="7" class="text-center py-4">Материалов пока нет.</td></tr>@endforelse
    </tbody></table>
</div>@if($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif</div>
@endsection
