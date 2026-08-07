<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
</head>
<body class="login-page bg-body-secondary">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center"><strong>NEWS MONITOR</strong></div>
        <div class="card-body login-card-body">
            <p class="login-box-msg">Вход в административную панель</p>
            @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
            <form method="post" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3"><label class="form-label" for="login">Логин</label><input class="form-control @error('login') is-invalid @enderror" id="login" type="text" name="login" value="{{ old('login') }}" maxlength="64" autocomplete="username" required autofocus></div>
                <div class="mb-3"><label class="form-label" for="password">Пароль</label><input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" value="1" id="remember"><label class="form-check-label" for="remember">Запомнить меня</label></div>
                <button class="btn btn-primary w-100" type="submit">Войти</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
