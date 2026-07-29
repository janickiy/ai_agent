@extends('admin.layout')

@section('title', 'Настройки агента')

@section('content')
<div class="card">
    <form method="post" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="collection-enabled" name="collection_enabled" value="1" @checked(old('collection_enabled', $settings['collection_enabled'])) @cannot('manage-settings') disabled @endcannot>
                <label class="form-check-label fw-semibold" for="collection-enabled">Сбор и обработка включены</label>
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="automatic-publication" name="automatic_publication" value="1" @checked(old('automatic_publication', $settings['automatic_publication'])) @cannot('manage-settings') disabled @endcannot>
                <label class="form-check-label fw-semibold" for="automatic-publication">Автоматическое создание публикаций</label>
                <div class="form-text">После прохождения проверок материал будет сохранён как готовая публикация.</div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold" for="max-news-age-hours">Максимальный возраст новости, часов</label>
                <input class="form-control" type="number" id="max-news-age-hours" name="max_news_age_hours" value="{{ old('max_news_age_hours', $settings['max_news_age_hours']) }}" min="1" max="8760" required @cannot('manage-settings') disabled @endcannot>
            </div>

            <div>
                <label class="form-label fw-semibold" for="event-similarity-threshold">Порог сходства событий</label>
                <input class="form-control" type="number" id="event-similarity-threshold" name="event_similarity_threshold" value="{{ old('event_similarity_threshold', $settings['event_similarity_threshold']) }}" min="0" max="1" step="0.01" required @cannot('manage-settings') disabled @endcannot>
                <div class="form-text">Материалы с равным или большим сходством считаются дубликатами одного события.</div>
            </div>
        </div>
        @can('manage-settings')
        <div class="card-footer">
            <button class="btn btn-primary" type="submit">Сохранить</button>
        </div>
        @endcan
    </form>
</div>
@endsection
