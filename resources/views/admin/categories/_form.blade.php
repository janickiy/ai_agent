@php
    $editing = isset($category);
    $keywords = old('keywords', $editing ? implode(PHP_EOL, $category->keywords ?? []) : '');
@endphp

<form method="post" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="row g-3">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="col-md-6">
        <label class="form-label" for="category-name">Название</label>
        <input class="form-control" id="category-name" name="name" value="{{ old('name', $category->name ?? '') }}" maxlength="255" required autofocus>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="category-code">Код</label>
        <input class="form-control" id="category-code" name="code" value="{{ old('code', $category->code ?? '') }}" maxlength="64" pattern="[a-z0-9]+(?:_[a-z0-9]+)*" placeholder="building_materials">
        <div class="form-text">Латинские буквы, цифры и подчёркивания.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="category-hashtag">Хэштег</label>
        <input class="form-control" id="category-hashtag" name="hashtag" value="{{ old('hashtag', $category->hashtag ?? '') }}" maxlength="128" placeholder="#Строительство">
    </div>
    <div class="col-12">
        <label class="form-label" for="category-keywords">Ключевые слова</label>
        <textarea class="form-control" id="category-keywords" name="keywords" rows="5" required>{{ is_array($keywords) ? implode(PHP_EOL, $keywords) : $keywords }}</textarea>
        <div class="form-text">По одному слову или фразе на строку. Также можно разделять запятыми.</div>
    </div>
    <div class="col-12">
        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
            <span class="form-check-label">Активна</span>
        </label>
    </div>
    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Сохранить' : 'Добавить тематику' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">Отмена</a>
    </div>
</form>
