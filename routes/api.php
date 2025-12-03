<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppointmentController;

Route::post('/webhook/whatsapp', [WebhookController::class, 'handleIncoming'])->name('webhook.whatsapp');
// Route::get('/send-reminders', function () {
//     return Artisan::call('reminders:send');
// });
Route::get('/send-reminders24', function () {
    return Artisan::call('reminders24:send');
});

// Авторизация (без токена)
Route::prefix('auth')->group(function () {
    Route::post('/send-code', [AuthController::class, 'sendCode']);
    Route::post('/verify-code', [AuthController::class, 'verifyCode']);
});

// Защищённые роуты (требуют токен)
Route::middleware('auth:sanctum')->group(function () {

    // Токен устройства
    Route::post('/device-token/update', [AuthController::class, 'updateDeviceToken']);

    // Выход
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Приёмы
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);
});
