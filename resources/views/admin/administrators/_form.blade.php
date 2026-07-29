@php($editing = isset($administrator))

<form method="post" action="{{ $editing ? route('admin.administrators.update', $administrator) : route('admin.administrators.store') }}" class="row g-3">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="col-md-6">
        <label class="form-label fw-semibold" for="administrator-name">Имя <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
            <input class="form-control @error('name') is-invalid @enderror" id="administrator-name" name="name" value="{{ old('name', $administrator->name ?? '') }}" maxlength="255" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="administrator-email">Email для входа <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
            <input class="form-control @error('email') is-invalid @enderror" type="email" id="administrator-email" name="email" value="{{ old('email', $administrator->email ?? '') }}" maxlength="255" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="administrator-password">Пароль @unless($editing)<span class="text-danger">*</span>@endunless</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key" aria-hidden="true"></i></span>
            <input class="form-control @error('password') is-invalid @enderror" type="password" id="administrator-password" name="password" @if(! $editing) required @endif autocomplete="new-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">{{ $editing ? 'Оставьте пустым, чтобы не менять пароль.' : 'Не менее 12 символов: строчные и заглавные буквы, цифры.' }}</div>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="administrator-password-confirmation">Повторите пароль @unless($editing)<span class="text-danger">*</span>@endunless</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key-fill" aria-hidden="true"></i></span>
            <input class="form-control" type="password" id="administrator-password-confirmation" name="password_confirmation" @if(! $editing) required @endif autocomplete="new-password">
        </div>
    </div>
    <div class="col-12">
        <div class="rounded border bg-body-tertiary p-3">
            <label class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $administrator->is_active ?? true)) @if($editing && auth()->user()->is($administrator)) disabled @endif>
                <span class="form-check-label fw-semibold">Активен и может входить в панель</span>
            </label>
        @if($editing && auth()->user()->is($administrator))
            <input type="hidden" name="is_active" value="1">
            <div class="form-text">Собственную учётную запись нельзя отключить.</div>
        @else
            <div class="form-text">Отключённый администратор сохраняется, но не может авторизоваться.</div>
        @endif
        </div>
    </div>
    <div class="col-12">
        <hr class="my-1">
        <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2 pt-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.administrators.index') }}">
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>
                Отмена
            </a>
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                {{ $editing ? 'Сохранить изменения' : 'Добавить администратора' }}
            </button>
        </div>
    </div>
</form>
