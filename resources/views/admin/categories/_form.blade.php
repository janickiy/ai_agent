@php
    $editing = isset($category);
    $keywords = old('keywords', $editing ? implode(PHP_EOL, $category->keywords ?? []) : '');
@endphp

<form method="post" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="row g-3">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="col-md-6">
        <label class="form-label fw-semibold" for="category-name">Название <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-card-heading" aria-hidden="true"></i></span>
            <input class="form-control @error('name') is-invalid @enderror" id="category-name" name="name" value="{{ old('name', $category->name ?? '') }}" maxlength="255" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold" for="category-code">Код</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-code-slash" aria-hidden="true"></i></span>
            <input class="form-control @error('code') is-invalid @enderror" id="category-code" name="code" value="{{ old('code', $category->code ?? '') }}" maxlength="64" pattern="[a-z0-9]+(?:_[a-z0-9]+)*" placeholder="building_materials">
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">Латинские буквы, цифры и подчёркивания.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold" for="category-hashtag">Хэштег</label>
        <div class="input-group">
            <span class="input-group-text">#</span>
            <input class="form-control @error('hashtag') is-invalid @enderror" id="category-hashtag" name="hashtag" value="{{ old('hashtag', $category->hashtag ?? '') }}" maxlength="128" placeholder="Строительство">
            @error('hashtag')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold" for="category-keywords">Ключевые слова <span class="text-danger">*</span></label>
        <textarea class="form-control @error('keywords') is-invalid @enderror" id="category-keywords" name="keywords" rows="6" required>{{ is_array($keywords) ? implode(PHP_EOL, $keywords) : $keywords }}</textarea>
        @error('keywords')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">По одному слову или фразе на строку. Также можно разделять запятыми.</div>
    </div>
    <div class="col-12">
        <div class="rounded border bg-body-tertiary p-3">
        <label class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
            <span class="form-check-label fw-semibold">Активная тематика</span>
        </label>
        <div class="form-text ms-0">Неактивная тематика сохраняется, но не используется при классификации материалов.</div>
        </div>
    </div>
    <div class="col-12">
        <hr class="my-1">
        <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2 pt-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>
                Отмена
            </a>
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                {{ $editing ? 'Сохранить изменения' : 'Добавить тематику' }}
            </button>
        </div>
    </div>
</form>
