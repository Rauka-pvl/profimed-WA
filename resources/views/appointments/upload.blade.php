@extends('layouts.app')

@section('title', 'Загрузка PDF - Profimed')
@section('page-title', 'Загрузка расписания врачей')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-arrow-up"></i> Загрузить PDF-файл
                </h5>
            </div>

            <div class="card-body">
                @if(session('stats'))
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle"></i> PDF успешно обработан!</h5>
                        <hr>
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h3 class="text-success">{{ session('stats')['added'] }}</h3>
                                <p class="mb-0">Добавлено</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-info">{{ session('stats')['updated'] }}</h3>
                                <p class="mb-0">Обновлено</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-warning">{{ session('stats')['skipped'] }}</h3>
                                <p class="mb-0">Пропущено</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('appointments.index') }}" class="btn btn-primary">
                                <i class="bi bi-calendar-check"></i> Перейти к приёмам
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Инструкция -->
                <div class="alert alert-info mb-4">
                    <h6><i class="bi bi-info-circle"></i> Инструкция:</h6>
                    <ol class="mb-0">
                        <li>Выберите PDF-файл с расписанием врачей</li>
                        <li>Нажмите кнопку "Загрузить и обработать"</li>
                        <li>Система автоматически:
                            <ul>
                                <li>Распарсит PDF-файл</li>
                                <li>Создаст или обновит записи врачей и пациентов</li>
                                <li>Добавит все приёмы в базу данных</li>
                            </ul>
                        </li>
                    </ol>
                </div>

                <!-- Форма загрузки -->
                <form method="POST"
                      action="{{ route('appointment.upload.process') }}"
                      enctype="multipart/form-data">
                    @csrf

                    <div class="mb-4">
                        <label for="pdf" class="form-label">PDF-файл с расписанием</label>
                        <input type="file"
                               name="pdf"
                               id="pdf"
                               class="form-control @error('pdf') is-invalid @enderror"
                               accept=".pdf"
                               required>
                        @error('pdf')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Максимальный размер файла: 10 МБ. Формат: PDF
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-upload"></i> Загрузить и обработать
                        </button>
                        <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Назад к приёмам
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Дополнительная информация -->
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-question-circle"></i> Формат PDF-файла
                </h6>
            </div>
            <div class="card-body">
                <p>PDF-файл должен содержать расписание в следующем формате:</p>
                <pre class="bg-light p-3 rounded"><code>19.09.2025 Черевко Татьяна
Время Кабинет Пациент Услуги
06:00 - 06:15 Колоноскопия ( кб. №309, 3 этаж ) Бегимова Елена Николаевна +7 777 7814117 Колоноскопия-12000
11:00 - 11:15 ФГДС ( кб. №309, 3 этаж ) Токтарова Арайлым Мейрамовна +7 747 3733116 ФГДС...</code></pre>

                <div class="alert alert-warning mb-0">
                    <strong>Важно:</strong> Каждая строка должна содержать:
                    <ul class="mb-0">
                        <li>Время приёма (формат: ЧЧ:ММ - ЧЧ:ММ)</li>
                        <li>Кабинет (опционально)</li>
                        <li>ФИО пациента</li>
                        <li>Номер телефона (формат: +7 XXX XXXXXXX)</li>
                        <li>Название услуги</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
