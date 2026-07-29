@extends('admin.layout')

@section('title', 'Настройки агента')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item active" aria-current="page">Настройки</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card card-primary card-outline shadow-sm settings-card">
            <form method="post" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="card-header">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                        <div>
                            <h3 class="card-title mb-1">
                                <i class="bi bi-sliders me-2 text-primary" aria-hidden="true"></i>
                                Параметры ИИ-агента
                            </h3>
                            <p class="mb-0 small text-body-secondary">
                                Управление сбором, созданием публикаций и правилами объединения событий.
                            </p>
                        </div>
                        @cannot('manage-settings')
                        <span class="badge text-bg-secondary ms-sm-auto">
                            <i class="bi bi-eye me-1" aria-hidden="true"></i>
                            Только просмотр
                        </span>
                        @endcannot
                    </div>
                </div>

                <div class="card-body">
                    <section aria-labelledby="agent-mode-heading">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="settings-section-icon text-bg-primary">
                                <i class="bi bi-power" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h4 class="h6 fw-bold mb-0" id="agent-mode-heading">Режим работы агента</h4>
                                <div class="small text-body-secondary">Основные переключатели автоматической обработки.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="settings-option rounded border bg-body-tertiary p-3 h-100">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="settings-option-icon text-bg-success">
                                            <i class="bi bi-broadcast" aria-hidden="true"></i>
                                        </span>
                                        <div class="flex-grow-1">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input" type="checkbox" role="switch" id="collection-enabled" name="collection_enabled" value="1" @checked(old('collection_enabled', $settings['collection_enabled'])) @cannot('manage-settings') disabled @endcannot>
                                                <label class="form-check-label fw-semibold" for="collection-enabled">Сбор и обработка включены</label>
                                            </div>
                                            <div class="form-text mt-0">Агент опрашивает активные источники и обрабатывает найденные материалы.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="settings-option rounded border bg-body-tertiary p-3 h-100">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="settings-option-icon text-bg-info">
                                            <i class="bi bi-send-check" aria-hidden="true"></i>
                                        </span>
                                        <div class="flex-grow-1">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input" type="checkbox" role="switch" id="automatic-publication" name="automatic_publication" value="1" @checked(old('automatic_publication', $settings['automatic_publication'])) @cannot('manage-settings') disabled @endcannot>
                                                <label class="form-check-label fw-semibold" for="automatic-publication">Автоматическое создание публикаций</label>
                                            </div>
                                            <div class="form-text mt-0">После проверок материал сохраняется в разделе готовых постов.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <hr class="my-4">

                    <section aria-labelledby="processing-settings-heading">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="settings-section-icon text-bg-primary">
                                <i class="bi bi-gear-wide-connected" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h4 class="h6 fw-bold mb-0" id="processing-settings-heading">Параметры обработки</h4>
                                <div class="small text-body-secondary">Ограничения актуальности и объединения материалов.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="max-news-age-hours">
                                    Максимальный возраст новости <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                                    <input class="form-control @error('max_news_age_hours') is-invalid @enderror" type="number" id="max-news-age-hours" name="max_news_age_hours" value="{{ old('max_news_age_hours', $settings['max_news_age_hours']) }}" min="1" max="8760" required @cannot('manage-settings') disabled @endcannot>
                                    <span class="input-group-text">часов</span>
                                    @error('max_news_age_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text">Более старые публикации не проходят проверку актуальности.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="event-similarity-threshold">
                                    Порог сходства событий <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-intersect" aria-hidden="true"></i></span>
                                    <input class="form-control @error('event_similarity_threshold') is-invalid @enderror" type="number" id="event-similarity-threshold" name="event_similarity_threshold" value="{{ old('event_similarity_threshold', $settings['event_similarity_threshold']) }}" min="0" max="1" step="0.01" required @cannot('manage-settings') disabled @endcannot>
                                    <span class="input-group-text">0–1</span>
                                    @error('event_similarity_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text">Материалы с равным или большим сходством считаются одним событием.</div>
                            </div>
                        </div>
                    </section>
                </div>

                @can('manage-settings')
                <div class="card-footer bg-body-tertiary">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-floppy-fill me-1" aria-hidden="true"></i>
                            Сохранить настройки
                        </button>
                    </div>
                </div>
                @endcan
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .settings-card .card-header {
        padding: 1rem 1.25rem;
    }
    .settings-card .card-title {
        float: none;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .settings-section-icon,
    .settings-option-icon {
        align-items: center;
        display: inline-flex;
        flex: 0 0 auto;
        justify-content: center;
    }
    .settings-section-icon {
        border-radius: .5rem;
        height: 2.25rem;
        width: 2.25rem;
    }
    .settings-option-icon {
        border-radius: 50%;
        height: 2.5rem;
        width: 2.5rem;
    }
    .settings-option {
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .settings-option:focus-within {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
    }
    .settings-card .form-switch .form-check-input {
        cursor: pointer;
    }
    .settings-card .form-switch .form-check-input:disabled {
        cursor: not-allowed;
    }
</style>
@endpush
