<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Мониторинг') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.bootstrap5.min.css">
    @stack('styles')
    <style>
        .app-sidebar { --lte-sidebar-width: 260px; }
        .brand-text { font-weight: 700; }
        .content-wrapper { min-height: 100vh; }
        .table td { vertical-align: middle; }
        .source-link { max-width: 420px; overflow-wrap: anywhere; }
        .category-table { min-width: 900px; table-layout: fixed; }
        .category-keywords { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .category-value { overflow-wrap: anywhere; }
        div.dt-container div.dt-layout-row { margin: 0; padding: 1rem; }
        div.dt-container div.dt-layout-row.dt-layout-table { padding: 0; }
        div.dt-container div.dt-layout-row:last-child { padding-bottom: 1.5rem; }
        div.dt-container div.dt-layout-row:first-child {
            align-items: center;
            min-height: 72px;
        }
        div.dt-container .dt-length label,
        div.dt-container .dt-search {
            align-items: center;
            display: flex;
            font-size: 1rem;
            gap: .55rem;
            margin: 0;
        }
        div.dt-container .dt-length select {
            min-width: 72px;
            order: 0;
        }
        div.dt-container .dt-search {
            justify-content: flex-end;
        }
        div.dt-container .dt-search::before {
            color: var(--bs-body-color);
            content: "\F52A";
            font-family: "bootstrap-icons";
            font-size: 1.45rem;
            font-weight: 700;
            line-height: 1;
        }
        div.dt-container .dt-search label {
            display: none;
        }
        div.dt-container .dt-search input {
            margin: 0;
            min-height: 40px;
            min-width: 220px;
        }
        div.dt-container .dt-info {
            font-size: 1rem;
        }
        div.dt-container .pagination {
            margin: 0;
        }
        div.dt-container .page-link {
            min-width: 42px;
            padding: .55rem .8rem;
            text-align: center;
        }
        table.dataTable {
            border-collapse: collapse !important;
            margin: 0 !important;
            width: 100% !important;
        }
        table.dataTable > thead > tr > th {
            background: var(--bs-body-bg);
            border-bottom-width: 2px;
            font-size: 1rem;
            font-weight: 700;
            padding: 1rem .85rem;
            white-space: nowrap;
        }
        table.dataTable > tbody > tr > td {
            padding: .85rem;
        }
        table.dataTable.table-striped > tbody > tr:nth-of-type(odd) > * {
            --bs-table-bg-type: rgba(var(--bs-secondary-bg-rgb), .72);
        }
        table.dataTable .btn-sm {
            min-height: 34px;
            min-width: 34px;
        }
        @media (max-width: 767.98px) {
            div.dt-container div.dt-layout-row:first-child {
                gap: .75rem;
            }
            div.dt-container .dt-search {
                justify-content: flex-start;
            }
            div.dt-container .dt-search input {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><button class="nav-link" data-lte-toggle="sidebar" type="button"><i class="bi bi-list"></i></button></li>
                <li class="nav-item d-none d-md-block"><span class="nav-link">ИИ-агент отраслевых новостей</span></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><span class="nav-link">{{ auth()->user()->name }} · {{ auth()->user()->role }}</span></li>
                <li class="nav-item">
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-link nav-link" type="submit">Выйти</button></form>
                </li>
            </ul>
        </div>
    </nav>
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="{{ route('admin.dashboard') }}" class="brand-link"><span class="brand-text">NEWS MONITOR</span></a></div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}"><i class="nav-icon bi bi-speedometer2"></i><p>Обзор</p></a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.categories.*')) active @endif" href="{{ route('admin.categories.index') }}"><i class="nav-icon bi bi-tags"></i><p>Тематики</p></a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.sources.*')) active @endif" href="{{ route('admin.sources.index') }}"><i class="nav-icon bi bi-rss"></i><p>Источники</p></a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.items.*')) active @endif" href="{{ route('admin.items.index') }}"><i class="nav-icon bi bi-newspaper"></i><p>Материалы</p></a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.posts.*')) active @endif" href="{{ route('admin.posts.index') }}"><i class="nav-icon bi bi-send-check"></i><p>Готовые посты</p></a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.logs.*')) active @endif" href="{{ route('admin.logs.index') }}"><i class="nav-icon bi bi-journal-text"></i><p>Журнал и ошибки</p></a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.settings.*')) active @endif" href="{{ route('admin.settings.edit') }}"><i class="nav-icon bi bi-sliders"></i><p>Настройки</p></a></li>
                    @can('manage-administrators')
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.administrators.*')) active @endif" href="{{ route('admin.administrators.index') }}"><i class="nav-icon bi bi-people"></i><p>Администраторы</p></a></li>
                    @endcan
                </ul>
            </nav>
        </div>
    </aside>
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid"><h1 class="mb-0">@yield('title')</h1></div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </div>
        </div>
    </main>
    <footer class="app-footer"><span>© {{ now()->year }} Яницкий Александр</span></footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/js/adminlte.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.bootstrap5.min.js"></script>
<script src="{{ asset('js/admin-datatables.js') }}"></script>
@stack('scripts')
</body>
</html>
