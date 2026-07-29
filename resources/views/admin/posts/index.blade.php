@extends('admin.layout')

@section('title', 'Готовые посты')

@section('content')
<div class="card"><div class="card-body table-responsive p-0">
    <table class="table table-striped mb-0"><thead><tr><th>Изображение</th><th>Публикация</th><th>Источник</th><th>Категория</th><th>Хэштеги</th><th>Статус</th></tr></thead><tbody>
    @forelse($posts as $post)
    <tr>
        <td>@if($post->image_url)<img src="{{ $post->image_url }}" alt="" width="96" height="64" style="object-fit:cover" referrerpolicy="no-referrer">@else<span class="text-secondary">нет</span>@endif</td>
        <td><strong>{{ $post->title_original }}</strong><br><small>{{ \Illuminate\Support\Str::limit($post->description_original, 180) }}</small></td>
        <td>{{ $post->source_name }}<br><a href="{{ $post->source_url }}" target="_blank" rel="noopener noreferrer">{{ $post->read_more_label }}</a><br><small>{{ $post->source_published_at->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</small></td>
        <td>{{ $post->category->name }}</td>
        <td>{{ implode(' ', $post->hashtags) }}</td>
        <td><span class="badge text-bg-success">{{ $post->status }}</span></td>
    </tr>
    @empty<tr><td colspan="6" class="text-center py-4">Готовых постов пока нет.</td></tr>@endforelse
    </tbody></table>
</div>@if($posts->hasPages())<div class="card-footer">{{ $posts->links() }}</div>@endif</div>
@endsection
