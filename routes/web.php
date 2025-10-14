<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PdfUploadController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Публичные маршруты
Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// Webhook для Green API (без аутентификации)
Route::post('/webhook/whatsapp', [WebhookController::class, 'handleIncoming'])->name('webhook.whatsapp');

// Защищённые маршруты (требуют аутентификации)
Route::middleware('auth')->group(function () {

    // Выход
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Appointments (Приёмы)
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');

    // PDF Upload
    Route::get('/appointment/upload', [PdfUploadController::class, 'showUploadForm'])->name('appointment.upload');
    Route::post('/appointment/upload', [PdfUploadController::class, 'upload'])->name('appointment.upload.process');

    // Doctors (Врачи)
    Route::resource('doctors', DoctorController::class);

    // Patients (Пациенты)
    Route::resource('patients', PatientController::class);
});
