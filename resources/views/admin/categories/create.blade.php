@extends('admin.layout')

@section('title', 'Добавление тематики')

@section('content')
<div class="card">
    <div class="card-header"><strong>Новая тематика</strong></div>
    <div class="card-body">
        @include('admin.categories._form')
    </div>
</div>
@endsection
