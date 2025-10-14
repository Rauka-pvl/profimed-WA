@extends('layouts.app')

@section('title', 'Dashboard - Profimed')
@section('page-title', 'Dashboard')

@section('content')
<div class="row g-4 mb-4">
    <!-- Статистика -->
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body text-center">
                <i class="bi bi-calendar-check fs-1"></i>
                <h3 class="mt-2">{{ $stats['total_appointments'] }}</h3>
                <p class="mb-0">Всего приёмов</p>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="bi bi-calendar-day fs-1"></i>
                <h3 class="mt-2">{{ $stats['today_appointments'] }}</h3>
                <p class="mb-0">Сегодня</p>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="bi bi-calendar-week fs-1"></i>
                <h3 class="mt-2">{{ $stats['week_appointments'] }}</h3>
                <p class="mb-0">На этой неделе</p>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="bi bi-people fs-1"></i>
                <h3 class="mt-2">{{ $stats['total_patients'] }}</h3>
                <p class="mb-0">Пациентов</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-person-badge fs-1 text-primary"></i>
                <h3 class="mt-2">{{ $stats['total_doctors'] }}</h3>
                <p class="mb-0">Врачей</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-check-circle fs-1 text-success"></i>
                <h3 class="mt-2">{{ $stats['confirmed'] }}</h3>
                <p class="mb-0">Подтверждено</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-x-circle fs-1 text-danger"></i>
                <h3 class="mt-2">{{ $stats['cancelled'] }}</h3>
                <p class="mb-0">Отменено</p>
            </div>
        </div>
    </div>
</div>

<!-- Ближайшие приёмы -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-calendar-event"></i> Ближайшие приёмы
        </h5>
    </div>
    <div class="card-body p-0">
        @if($upcomingAppointments->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Дата</th>
                            <th>Время</th>
                            <th>Пациент</th>
                            <th>Врач</th>
                            <th>Кабинет</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($upcomingAppointments as $appointment)
                            <tr>
                                <td>{{ $appointment->date->format('d.m.Y') }}</td>
                                <td>{{ $appointment->time }}</td>
                                <td>
                                    <i class="bi bi-person"></i>
                                    {{ $appointment->patient->full_name }}
                                </td>
                                <td>
                                    <i class="bi bi-person-badge"></i>
                                    {{ $appointment->doctor->name }}
                                </td>
                                <td>
                                    @if($appointment->cabinet)
                                        <span class="badge bg-secondary">{{ $appointment->cabinet }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($appointment->status === 'scheduled')
                                        <span class="badge bg-warning">Запланирован</span>
                                    @elseif($appointment->status === 'confirmed')
                                        <span class="badge bg-success">Подтверждён</span>
                                    @else
                                        <span class="badge bg-danger">Отменён</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-1"></i>
                <p class="mt-2">Нет предстоящих приёмов</p>
            </div>
        @endif
    </div>
</div>
@endsection
