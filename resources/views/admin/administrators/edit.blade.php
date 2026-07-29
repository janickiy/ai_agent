@extends('admin.layout')

@section('title', 'Редактирование администратора')

@section('content')
<div class="card">
    <div class="card-header"><strong>{{ $administrator->name }}</strong></div>
    <div class="card-body">
        @include('admin.administrators._form')
    </div>
</div>
@endsection
