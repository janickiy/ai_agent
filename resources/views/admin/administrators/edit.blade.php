@extends('admin.layout')

@section('title', 'Редактирование администратора')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.administrators.index') }}">Администраторы</a></li>
    <li class="breadcrumb-item active" aria-current="page">Редактирование</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xl-9">
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-person-gear me-2 text-primary" aria-hidden="true"></i>
                    {{ $administrator->name }}
                </h3>
            </div>
            <div class="card-body">
                @include('admin.administrators._form')
            </div>
        </div>
    </div>
</div>
@endsection
