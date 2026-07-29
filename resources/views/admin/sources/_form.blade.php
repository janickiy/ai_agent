@php
    $editing = isset($source);
    $selectedCategoryIds = array_map(
        'intval',
        old('category_ids', $editing ? $source->categories->modelKeys() : $categories->modelKeys()),
    );
@endphp

<form method="post" action="{{ $editing ? route('admin.sources.update', $source) : route('admin.sources.store') }}" class="row g-3">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="col-lg-4">
        <label class="form-label fw-semibold" for="source-name">Название <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-card-heading" aria-hidden="true"></i></span>
            <input class="form-control @error('name') is-invalid @enderror" id="source-name" name="name" value="{{ old('name', $source->name ?? '') }}" maxlength="255" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-lg-4">
        <label class="form-label fw-semibold" for="source-domain">Домен <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-globe2" aria-hidden="true"></i></span>
            <input class="form-control @error('domain') is-invalid @enderror" id="source-domain" name="domain" value="{{ old('domain', $source->domain ?? '') }}" maxlength="190" placeholder="example.org" required>
            @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-lg-4">
        <label class="form-label fw-semibold" for="source-poll-interval">Период поиска, минут <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-clock" aria-hidden="true"></i></span>
            <input class="form-control @error('poll_interval_minutes') is-invalid @enderror" id="source-poll-interval" type="number" name="poll_interval_minutes" value="{{ old('poll_interval_minutes', $source->poll_interval_minutes ?? 30) }}" min="1" max="1440" required>
            @error('poll_interval_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <input type="hidden" name="type" value="{{ old('type', $source->type ?? 'rss') }}">

    <div class="col-lg-4">
        <label class="form-label fw-semibold" for="source-class">Тип источника <span class="text-danger">*</span></label>
        <select class="form-select @error('source_class') is-invalid @enderror" id="source-class" name="source_class" required>
            @foreach($sourceClasses as $code => $label)
            <option value="{{ $code }}" @selected(old('source_class', $source->source_class ?? '') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        @error('source_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-lg-2">
        <label class="form-label fw-semibold" for="source-trust">Доверие, % <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
            <input class="form-control @error('trust_score') is-invalid @enderror" id="source-trust" type="number" name="trust_score" value="{{ old('trust_score', $source->trust_score ?? 70) }}" min="0" max="100" required>
            @error('trust_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-semibold" for="source-base-url">Базовый URL <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>
            <input class="form-control @error('base_url') is-invalid @enderror" id="source-base-url" type="url" name="base_url" value="{{ old('base_url', $source->base_url ?? '') }}" placeholder="https://example.org" required>
            @error('base_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold" for="source-feed-url">URL RSS/Atom-ленты</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-rss-fill" aria-hidden="true"></i></span>
            <input class="form-control @error('feed_url') is-invalid @enderror" id="source-feed-url" type="url" name="feed_url" value="{{ old('feed_url', $source->feed_url ?? '') }}" placeholder="https://example.org/feed">
            @error('feed_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">Без URL источник остаётся в каталоге, но не опрашивается автоматически.</div>
    </div>

    <input type="hidden" name="request_limit" value="{{ $source->request_limit ?? 30 }}">
    <input type="hidden" name="timeout_seconds" value="{{ $source->timeout_seconds ?? 20 }}">
    <input type="hidden" name="max_attempts" value="{{ $source->max_attempts ?? 3 }}">

    <div class="col-12">
        <div class="card bg-body-tertiary border shadow-none mb-0">
            <div class="card-header border-bottom">
                <h4 class="card-title mb-0">
                    <i class="bi bi-tags me-2 text-primary" aria-hidden="true"></i>
                    Тематики
                </h4>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($categories as $category)
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <label class="form-check rounded border bg-body p-2 ps-5 mb-0 h-100">
                            <input class="form-check-input" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategoryIds, true))>
                            <span class="form-check-label">{{ $category->name }}</span>
                        </label>
                    </div>
                    @endforeach
                </div>
                @error('category_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="rounded border bg-body-tertiary p-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $source->is_active ?? true))>
                        <span class="form-check-label fw-semibold">Активен</span>
                    </label>
                    <div class="form-text">Источник участвует в автоматическом мониторинге.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_allowed" value="1" @checked(old('is_allowed', $source->is_allowed ?? false))>
                        <span class="form-check-label fw-semibold">Использование согласовано</span>
                    </label>
                    <div class="form-text">Есть разрешение на обработку материалов.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_trusted" value="1" @checked(old('is_trusted', $source->is_trusted ?? false))>
                        <span class="form-check-label fw-semibold">Доверенный</span>
                    </label>
                    <div class="form-text">Источник имеет повышенный уровень доверия.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <hr class="my-1">
        <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2 pt-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.sources.index') }}">
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>
                Отмена
            </a>
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                {{ $editing ? 'Сохранить изменения' : 'Добавить источник' }}
            </button>
        </div>
    </div>
</form>
