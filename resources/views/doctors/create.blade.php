@extends('layouts.app')

@section('title', 'Добавить врача - Profimed')
@section('page-title', 'Добавить нового врача')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-person-badge"></i> Новый врач
                </h5>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('doctors.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">ФИО врача <span class="text-danger">*</span></label>
                        <input type="text"
                               name="name"
                               id="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}"
                               required
                               autofocus
                               placeholder="Иванов Иван Иванович">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить
                        </button>
                        <a href="{{ route('doctors.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Назад к списку
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
