@extends('admin.layout')

@section('title', 'Добавление источника')

@section('content')
<div class="card">
    <div class="card-header"><strong>Новый источник</strong></div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.sources.store') }}" class="row g-3">
            @csrf
            <div class="col-md-4"><label class="form-label">Название</label><input class="form-control" name="name" value="{{ old('name') }}" required></div>
            <div class="col-md-4"><label class="form-label">Домен</label><input class="form-control" name="domain" value="{{ old('domain') }}" placeholder="example.org" required></div>
            <div class="col-md-4"><label class="form-label">Период поиска, минут</label><input class="form-control" type="number" name="poll_interval_minutes" value="{{ old('poll_interval_minutes', 30) }}" min="1" required></div>
            <input type="hidden" name="type" value="rss">
            <div class="col-md-4">
                <label class="form-label">Тип источника</label>
                <select class="form-select" name="source_class">
                    @foreach($sourceClasses as $code => $label)
                    <option value="{{ $code }}" @selected(old('source_class') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Доверие, %</label><input class="form-control" type="number" name="trust_score" value="{{ old('trust_score', 70) }}" min="0" max="100" required></div>
            <div class="col-md-6"><label class="form-label">Базовый URL</label><input class="form-control" type="url" name="base_url" value="{{ old('base_url') }}" required></div>
            <div class="col-md-6"><label class="form-label">URL RSS/Atom-ленты</label><input class="form-control" type="url" name="feed_url" value="{{ old('feed_url') }}"><div class="form-text">Можно оставить пустым: источник останется в каталоге без автоматического опроса.</div></div>
            <input type="hidden" name="request_limit" value="30">
            <input type="hidden" name="timeout_seconds" value="20">
            <input type="hidden" name="max_attempts" value="3">
            <div class="col-12">
                <label class="form-label d-block">Тематики</label>
                @foreach($categories as $category)
                <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, array_map('intval', old('category_ids', $categories->modelKeys())), true))> {{ $category->name }}</label>
                @endforeach
            </div>
            <div class="col-12">
                <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Активен</label>
                <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_allowed" value="1" @checked(old('is_allowed', false))> Использование согласовано</label>
                <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_trusted" value="1" @checked(old('is_trusted', false))> Доверенный</label>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Добавить источник</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.sources.index') }}">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection
