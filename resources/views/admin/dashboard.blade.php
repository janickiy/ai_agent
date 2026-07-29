@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
<div class="alert alert-{{ $status['class'] }} d-flex justify-content-between align-items-center">
    <div><strong>Состояние системы: {{ $status['label'] }}</strong>
        @if($status['reasons'])<span class="ms-2">{{ implode(' · ', $status['reasons']) }}</span>@endif
    </div>
    <small>Рассчитано {{ now()->timezone(config('app.display_timezone'))->format('d.m.Y H:i:s') }}</small>
</div>
<div class="row">
    @foreach($metrics as $label => $value)
    <div class="col-xl-3 col-md-4 col-sm-6">
        <div class="small-box text-bg-primary">
            <div class="inner"><h3>{{ $value }}</h3><p>{{ $label }}</p></div>
            <div class="small-box-icon"><i class="bi bi-graph-up-arrow"></i></div>
        </div>
    </div>
    @endforeach
</div>
@endsection
