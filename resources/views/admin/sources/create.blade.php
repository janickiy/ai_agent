@extends('admin.layout')

@section('title', 'Добавление источника')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.sources.index') }}">Источники</a></li>
    <li class="breadcrumb-item active" aria-current="page">Добавление</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xl-11">
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-plus-circle me-2 text-primary" aria-hidden="true"></i>
                    Новый источник
                </h3>
            </div>
            <div class="card-body">
                @include('admin.sources._form')
            </div>
        </div>
    </div>
</div>
@endsection
