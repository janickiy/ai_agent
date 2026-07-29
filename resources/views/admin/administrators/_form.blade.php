@php($editing = isset($administrator))

<form method="post" action="{{ $editing ? route('admin.administrators.update', $administrator) : route('admin.administrators.store') }}" class="row g-3">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="col-md-6">
        <label class="form-label" for="administrator-name">Имя</label>
        <input class="form-control" id="administrator-name" name="name" value="{{ old('name', $administrator->name ?? '') }}" maxlength="255" required autofocus>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="administrator-email">Email для входа</label>
        <input class="form-control" type="email" id="administrator-email" name="email" value="{{ old('email', $administrator->email ?? '') }}" maxlength="255" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="administrator-password">Пароль</label>
        <input class="form-control" type="password" id="administrator-password" name="password" @if(! $editing) required @endif autocomplete="new-password">
        <div class="form-text">{{ $editing ? 'Оставьте пустым, чтобы не менять пароль.' : 'Не менее 12 символов: строчные и заглавные буквы, цифры.' }}</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="administrator-password-confirmation">Повторите пароль</label>
        <input class="form-control" type="password" id="administrator-password-confirmation" name="password_confirmation" @if(! $editing) required @endif autocomplete="new-password">
    </div>
    <div class="col-12">
        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $administrator->is_active ?? true)) @if($editing && auth()->user()->is($administrator)) disabled @endif>
            <span class="form-check-label">Активен и может входить в панель</span>
        </label>
        @if($editing && auth()->user()->is($administrator))
        <input type="hidden" name="is_active" value="1">
        <div class="form-text">Собственную учетную запись нельзя отключить.</div>
        @endif
    </div>
    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Сохранить' : 'Добавить администратора' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.administrators.index') }}">Отмена</a>
    </div>
</form>
