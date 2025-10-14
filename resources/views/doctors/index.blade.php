@extends('layouts.app')

@section('title', 'Врачи - Profimed')
@section('page-title', 'Управление врачами')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0">
                    <i class="bi bi-person-badge"></i> Список врачей
                </h5>
            </div>
            <div class="col text-end">
                <a href="{{ route('doctors.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Добавить врача
                </a>
            </div>
        </div>
    </div>

    <div class="card-body">
        @if($doctors->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ФИО врача</th>
                            <th>Количество приёмов</th>
                            <th>Дата добавления</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($doctors as $doctor)
                            <tr>
                                <td>
                                    <i class="bi bi-person-badge text-primary"></i>
                                    <strong>{{ $doctor->name }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ $doctor->appointments_count }} приёмов
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ $doctor->created_at->format('d.m.Y') }}
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('doctors.edit', $doctor) }}"
                                           class="btn btn-warning"
                                           title="Редактировать">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('doctors.destroy', $doctor) }}"
                                              onsubmit="return confirm('Удалить врача? Все связанные приёмы также будут удалены!')">
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
                {{ $doctors->links() }}
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-person-badge fs-1"></i>
                <p class="mt-2">Врачи не найдены</p>
                <a href="{{ route('doctors.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Добавить первого врача
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
