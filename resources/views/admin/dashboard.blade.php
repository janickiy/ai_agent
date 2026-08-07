@extends('admin.layout')

@section('title', 'Панель управления')

@section('content')
<div class="row g-3 mb-4">
    @foreach($metrics as $metric)
    <div class="col-xl-2 col-md-4 col-sm-6">
        <a class="small-box dashboard-metric-link text-bg-{{ $metric['class'] }} mb-0 h-100" href="{{ $metric['url'] }}">
            <div class="inner">
                <h3>{{ $metric['value'] }}</h3>
                <p class="fw-semibold">{{ $metric['label'] }}</p>
            </div>
            <div class="small-box-icon"><i class="bi {{ $metric['icon'] }}" aria-hidden="true"></i></div>
            <span class="small-box-footer">
                Перейти
                <i class="bi bi-arrow-right-circle ms-1" aria-hidden="true"></i>
            </span>
        </a>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Последние события</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Событие</th>
                            <th>Источник</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>{{ $event->id }}</td>
                            <td>
                                <strong>{{ $event->title }}</strong>
                                <br><small class="text-secondary">{{ \Carbon\Carbon::parse($event->event_at ?? $event->created_at)->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</small>
                            </td>
                            <td>
                                {{ $event->source_name ?? '—' }}
                                @if($event->items_count > 1)<br><small class="text-secondary">Материалов: {{ $event->items_count }}</small>@endif
                            </td>
                            <td><span class="badge text-bg-{{ $event->items_count > 0 ? 'success' : 'secondary' }}">{{ $event->items_count > 0 ? 'сформировано' : 'пустое' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">Событий пока нет.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Состояние агента</h3></div>
            <div class="card-body">
                <p>
                    Автопубликация:
                    <span class="badge text-bg-{{ $agent['automatic_publication'] ? 'success' : 'secondary' }}">{{ $agent['automatic_publication'] ? 'включена' : 'режим проверки' }}</span>
                </p>
                <p>ИИ-токены: <strong>{{ number_format($agent['ai_tokens'], 0, ',', ' ') }}</strong></p>
                <p>Расчётная стоимость: <strong>${{ number_format($agent['estimated_cost'], 4, '.', '') }}</strong></p>
                <hr>
                @foreach($agent['stages'] as $stage => $count)
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span>{{ $stage }}</span>
                    <span class="badge text-bg-info">{{ $count }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .dashboard-metric-link {
        cursor: pointer;
        display: block;
        text-decoration: none;
        transition: box-shadow .2s ease, transform .2s ease;
    }
    .dashboard-metric-link:hover {
        box-shadow: var(--bs-box-shadow);
        transform: translateY(-2px);
    }
    .dashboard-metric-link:focus-visible {
        outline: 3px solid rgba(13, 110, 253, .45);
        outline-offset: 3px;
    }
</style>
@endpush
