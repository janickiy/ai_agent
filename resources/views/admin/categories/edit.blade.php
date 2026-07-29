@extends('admin.layout')

@section('title', 'Редактирование тематики')

@section('content')
<div class="card">
    <div class="card-header"><strong>{{ $category->name }}</strong></div>
    <div class="card-body">
        @include('admin.categories._form')
    </div>
</div>
@endsection
