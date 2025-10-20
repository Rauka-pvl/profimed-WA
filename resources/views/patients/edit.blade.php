@extends('layouts.app')

@section('title', 'Редактировать пациента - Profimed')
@section('page-title', 'Редактировать пациента')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-pencil"></i> Редактирование пациента
                </h5>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('patients.update', $patient) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="full_name" class="form-label">ФИО пациента <span class="text-danger">*</span></label>
                        <input type="text"
                               name="full_name"
                               id="full_name"
                               class="form-control @error('full_name') is-invalid @enderror"
                               value="{{ old('full_name', $patient->full_name) }}"
                               required
                               autofocus>
                        @error('full_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Номер телефона</label>
                        <input type="text"
                               name="phone"
                               id="phone"
                               class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $patient->phone) }}">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Формат: +7 XXX XXX XX XX</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить изменения
                        </button>
                        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Назад к списку
                        </a>
                    </div>
                </form>

                @if (Auth::user()->role == 1)
                    <hr class="my-4">

                    <form method="POST"
                        action="{{ route('patients.destroy', $patient) }}"
                        onsubmit="return confirm('Удалить пациента? Все связанные приёмы также будут удалены!')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-trash"></i> Удалить пациента
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
