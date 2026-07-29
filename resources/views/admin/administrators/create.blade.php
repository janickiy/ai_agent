@extends('admin.layout')

@section('title', 'Добавление администратора')

@section('content')
<div class="card">
    <div class="card-header"><strong>Новый администратор</strong></div>
    <div class="card-body">
        @include('admin.administrators._form')
    </div>
</div>
@endsection
