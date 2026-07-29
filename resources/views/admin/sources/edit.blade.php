@extends('admin.layout')

@section('title', 'Редактирование источника')

@section('content')
<div class="card">
    <div class="card-header"><strong>{{ $source->name }}</strong></div>
    <div class="card-body">
        @php
            $selectedCategoryIds = array_map('intval', old('category_ids', $source->categories->modelKeys()));
        @endphp
        <form method="post" action="{{ route('admin.sources.update', $source) }}" class="row g-3">
            @csrf
            @method('PUT')
            <div class="col-md-4"><label class="form-label">Название</label><input class="form-control" name="name" value="{{ old('name', $source->name) }}" required></div>
            <div class="col-md-4"><label class="form-label">Домен</label><input class="form-control" name="domain" value="{{ old('domain', $source->domain) }}" required></div>
            <div class="col-md-4"><label class="form-label">Период поиска, минут</label><input class="form-control" type="number" name="poll_interval_minutes" value="{{ old('poll_interval_minutes', $source->poll_interval_minutes) }}" min="1" required></div>
            <input type="hidden" name="type" value="{{ $source->type }}">
            <div class="col-md-4">
                <label class="form-label">Тип источника</label>
                <select class="form-select" name="source_class">
                    @foreach($sourceClasses as $code => $label)
                    <option value="{{ $code }}" @selected(old('source_class', $source->source_class) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Доверие, %</label><input class="form-control" type="number" name="trust_score" value="{{ old('trust_score', $source->trust_score) }}" min="0" max="100" required></div>
            <div class="col-md-6"><label class="form-label">Базовый URL</label><input class="form-control" type="url" name="base_url" value="{{ old('base_url', $source->base_url) }}" required></div>
            <div class="col-md-6"><label class="form-label">URL RSS/Atom-ленты</label><input class="form-control" type="url" name="feed_url" value="{{ old('feed_url', $source->feed_url) }}"><div class="form-text">Без URL источник остаётся в каталоге, но не опрашивается автоматически.</div></div>
            <input type="hidden" name="request_limit" value="{{ $source->request_limit }}">
            <input type="hidden" name="timeout_seconds" value="{{ $source->timeout_seconds }}">
            <input type="hidden" name="max_attempts" value="{{ $source->max_attempts }}">
            <div class="col-12">
                <label class="form-label d-block">Тематики</label>
                @foreach($categories as $category)
                <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategoryIds, true))> {{ $category->name }}</label>
                @endforeach
            </div>
            <div class="col-12">
                <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $source->is_active))> Активен</label>
                <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_allowed" value="1" @checked(old('is_allowed', $source->is_allowed))> Использование согласовано</label>
                <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_trusted" value="1" @checked(old('is_trusted', $source->is_trusted))> Доверенный</label>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Сохранить</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.sources.index') }}">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection
