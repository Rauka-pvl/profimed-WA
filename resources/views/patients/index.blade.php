@extends('layouts.app')

@section('title', 'Пациенты - Profimed')
@section('page-title', 'Управление пациентами')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0">
                    <i class="bi bi-people"></i> Список пациентов
                </h5>
            </div>
            <div class="col text-end">
                <a href="{{ route('patients.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Добавить пациента
                </a>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Поиск -->
        <form method="GET" action="{{ route('patients.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-md-10">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Поиск по ФИО или номеру телефона..."
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Найти
                    </button>
                </div>
            </div>
        </form>

        @if($patients->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ФИО пациента</th>
                            <th>Телефон</th>
                            <th>Количество приёмов</th>
                            <th>Дата добавления</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($patients as $patient)
                            <tr>
                                <td>
                                    <i class="bi bi-person text-success"></i>
                                    <strong>{{ $patient->full_name }}</strong>
                                </td>
                                <td>
                                    @if($patient->phone)
                                        <a href="tel:{{ $patient->phone }}">
                                            <i class="bi bi-telephone"></i> {{ $patient->phone }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ $patient->appointments_count }} приёмов
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ $patient->created_at->format('d.m.Y') }}
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('patients.edit', $patient) }}"
                                           class="btn btn-warning"
                                           title="Редактировать">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if (Auth::user()->role == 1)
                                            <form method="POST"
                                                action="{{ route('patients.destroy', $patient) }}"
                                                onsubmit="return confirm('Удалить пациента? Все связанные приёмы также будут удалены!')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-danger"
                                                        title="Удалить">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            <div class="mt-4">
                {{ $patients->links() }}
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1"></i>
                <p class="mt-2">Пациенты не найдены</p>
                <a href="{{ route('patients.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Добавить первого пациента
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
