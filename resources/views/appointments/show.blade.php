@extends('layouts.app')

@section('title', 'Просмотр приёма - Profimed')
@section('page-title', 'Детали приёма')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-check"></i> Приём #{{ $appointment->id }}
                    </h5>
                    <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Назад
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Информация о приёме</h6>

                        <div class="mb-3">
                            <label class="text-muted small">Дата</label>
                            <div class="fs-5">
                                <i class="bi bi-calendar"></i>
                                {{ $appointment->date->format('d.m.Y') }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small">Время</label>
                            <div class="fs-5">
                                <i class="bi bi-clock"></i>
                                {{ $appointment->time }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small">Кабинет</label>
                            <div class="fs-5">
                                @if($appointment->cabinet)
                                    <i class="bi bi-door-open"></i>
                                    {{ $appointment->cabinet }}
                                @else
                                    <span class="text-muted">Не указан</span>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small">Статус</label>
                            <div>
                                @if($appointment->status === 'scheduled')
                                    <span class="badge bg-warning fs-6">Запланирован</span>
                                @elseif($appointment->status === 'confirmed')
                                    <span class="badge bg-success fs-6">Подтверждён</span>
                                @else
                                    <span class="badge bg-danger fs-6">Отменён</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Напоминания</h6>

                        <div class="mb-3">
                            <label class="text-muted small">За 24 часа</label>
                            <div>
                                @if($appointment->reminder_24h_sent)
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Отправлено
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-x-circle"></i> Не отправлено
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small">За 3 часа</label>
                            <div>
                                @if($appointment->reminder_3h_sent)
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Отправлено
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-x-circle"></i> Не отправлено
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="mb-4">
                    <h6 class="text-muted mb-3">Услуга</h6>
                    @if($appointment->service)
                        <p class="mb-0">{{ $appointment->service }}</p>
                    @else
                        <p class="text-muted mb-0">Не указана</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Информация о пациенте -->
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-person"></i> Пациент
                </h6>
            </div>
            <div class="card-body">
                <h5>{{ $appointment->patient->full_name }}</h5>

                @if($appointment->patient->phone)
                    <div class="mb-2">
                        <i class="bi bi-telephone"></i>
                        <a href="tel:{{ $appointment->patient->phone }}">
                            {{ $appointment->patient->phone }}
                        </a>
                    </div>
                @endif

                <a href="{{ route('patients.edit', $appointment->patient) }}"
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-pencil"></i> Редактировать
                </a>
            </div>
        </div>

        <!-- Информация о враче -->
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-person-badge"></i> Врач
                </h6>
            </div>
            <div class="card-body">
                <h5>{{ $appointment->doctor->name }}</h5>

                <a href="{{ route('doctors.edit', $appointment->doctor) }}"
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-pencil"></i> Редактировать
                </a>
            </div>
        </div>

        <!-- Действия -->
        @if (Auth::user()->role == 1)
            <div class="card mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="bi bi-gear"></i> Действия
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST"
                        action="{{ route('appointments.destroy', $appointment) }}"
                        onsubmit="return confirm('Вы уверены, что хотите удалить этот приём?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-trash"></i> Удалить приём
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
