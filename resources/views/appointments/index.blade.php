@extends('layouts.app')

@section('title', 'Приёмы - Profimed')
@section('page-title', 'Управление приёмами')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-check"></i> Список приёмов
                </h5>
            </div>
            <div class="col text-end">
                <a href="{{ route('appointment.upload') }}" class="btn btn-primary">
                    <i class="bi bi-file-earmark-arrow-up"></i> Загрузить PDF
                </a>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Фильтры -->
        <form method="GET" action="{{ route('appointments.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Поиск по пациенту, врачу, телефону..."
                           value="{{ request('search') }}">
                </div>

                <div class="col-md-3">
                    <input type="date"
                           name="date"
                           class="form-control"
                           value="{{ request('date') }}">
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Все статусы</option>
                        <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>
                            Запланирован
                        </option>
                        <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>
                            Подтверждён
                        </option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>
                            Отменён
                        </option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Найти
                    </button>
                </div>
            </div>
        </form>

        <!-- Таблица приёмов -->
        @if($appointments->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Дата</th>
                            <th>Время</th>
                            <th>Пациент</th>
                            <th>Телефон</th>
                            <th>Врач</th>
                            <th>Кабинет</th>
                            <th>Статус</th>
                            <th>Напоминания</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appointments as $appointment)
                            <tr>
                                <td>{{ $appointment->date->format('d.m.Y') }}</td>
                                <td><strong>{{ $appointment->time }}</strong></td>
                                <td>
                                    <i class="bi bi-person"></i>
                                    {{ $appointment->patient->full_name }}
                                </td>
                                <td>
                                    @if($appointment->patient->phone)
                                        <a href="tel:{{ $appointment->patient->phone }}">
                                            {{ $appointment->patient->phone }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
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
                                    <form method="POST"
                                          action="{{ route('appointments.updateStatus', $appointment) }}"
                                          class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status"
                                                class="form-select form-select-sm"
                                                onchange="this.form.submit()">
                                            <option value="scheduled" {{ $appointment->status === 'scheduled' ? 'selected' : '' }}>
                                                Запланирован
                                            </option>
                                            <option value="confirmed" {{ $appointment->status === 'confirmed' ? 'selected' : '' }}>
                                                Подтверждён
                                            </option>
                                            <option value="cancelled" {{ $appointment->status === 'cancelled' ? 'selected' : '' }}>
                                                Отменён
                                            </option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        @if($appointment->reminder_24h_sent)
                                            <span class="badge bg-success" title="24ч отправлено">24ч</span>
                                        @else
                                            <span class="badge bg-secondary" title="24ч не отправлено">24ч</span>
                                        @endif

                                        @if($appointment->reminder_3h_sent)
                                            <span class="badge bg-success" title="3ч отправлено">3ч</span>
                                        @else
                                            <span class="badge bg-secondary" title="3ч не отправлено">3ч</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('appointments.show', $appointment) }}"
                                           class="btn btn-info"
                                           title="Просмотр">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('appointments.destroy', $appointment) }}"
                                              onsubmit="return confirm('Удалить этот приём?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-danger"
                                                    title="Удалить">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            <div class="mt-4">
                {{ $appointments->links() }}
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-1"></i>
                <p class="mt-2">Приёмы не найдены</p>
                <a href="{{ route('appointment.upload') }}" class="btn btn-primary">
                    <i class="bi bi-file-earmark-arrow-up"></i> Загрузить PDF
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
