@extends('layouts.app')

@section('title', 'Редактировать врача - Profimed')
@section('page-title', 'Редактировать врача')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-pencil"></i> Редактирование врача
                </h5>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('doctors.update', $doctor) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">ФИО врача <span class="text-danger">*</span></label>
                        <input type="text"
                               name="name"
                               id="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $doctor->name) }}"
                               required
                               autofocus>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить изменения
                        </button>
                        <a href="{{ route('doctors.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Назад к списку
                        </a>
                    </div>
                </form>

                @if (Auth::user()->role == 1)
                    <hr class="my-4">
                    <form method="POST"
                        action="{{ route('doctors.destroy', $doctor) }}"
                        onsubmit="return confirm('Удалить врача? Все связанные приёмы также будут удалены!')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-trash"></i> Удалить врача
                        </button>
                    </form>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
