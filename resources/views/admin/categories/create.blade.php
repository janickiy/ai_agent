@extends('admin.layout')

@section('title', 'Добавление тематики')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Тематики</a></li>
    <li class="breadcrumb-item active" aria-current="page">Добавление</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-plus-circle me-2 text-primary" aria-hidden="true"></i>
                    Новая тематика
                </h3>
            </div>
            <div class="card-body">
                @include('admin.categories._form')
            </div>
        </div>
    </div>
</div>
@endsection
