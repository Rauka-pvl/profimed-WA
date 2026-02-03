<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\MobileAppointmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\NotificationController;

Route::get('/test', function () {
    $token = "dkCR9FZOTSeDQihk2NcOZd:APA91bEVX-5LXF8PSoeSUdt9yh52KMjbe2C6cBBG-x15deS7RL3HbHn05VnegIHOoDp4kJAAU5U75leoCbO_BBy4rycT2P8HR-2s8CRaGB7hLLBUwOkFLOI";

    return app(\App\Services\FirebaseService::class)
        ->sendNotification(
            $token,
            'Привет 👋',
            'Сообщение из Laravel',
            [
                'type' => 'order',
                'id' => '25'
            ]
        );
});


// Отправка напоминаний
Route::post('/webhook/whatsapp', [WebhookController::class, 'handleIncoming'])->name('webhook.whatsapp');
// Route::get('/send-reminders', function () {
//     return Artisan::call('reminders:send');
// });
Route::get('/send-reminders-firebase-24h', function () {
    return Artisan::call('reminders-firebase-24h:send');
});
Route::get('/send-reminders-firebase-3h', function () {
    return Artisan::call('reminders-firebase-3h:send');
});

// Авторизация (без токена)
Route::prefix('auth')->group(function () {
    Route::post('/send-code', [AuthController::class, 'sendCode']);
    Route::post('/verify-code', [AuthController::class, 'verifyCode']);
});

// Защищённые роуты (требуют токен)
Route::middleware('auth:sanctum')->group(function () {

    // Выход
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Мобильное API
    Route::prefix('mobile')->group(function () {
        // Профиль пациента
        Route::get('/patient/profile', [PatientController::class, 'profile']);
        Route::put('/patient/profile', [PatientController::class, 'update']);

        Route::post('/device-token/update', [AuthController::class, 'deviceToken']);

        // Записи (appointments)
        Route::get('/appointments', [MobileAppointmentController::class, 'index']);
        Route::get('/appointments/{id}', [MobileAppointmentController::class, 'show']);
        Route::post('/appointments/{id}/confirm', [MobileAppointmentController::class, 'confirm']);
        Route::post('/appointments/{id}/decline', [MobileAppointmentController::class, 'decline']);

        // Уведомления
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/send', [NotificationController::class, 'send']);
        Route::get('/notifications/settings', [NotificationController::class, 'settings']);
        Route::post('/notifications/send-status-24/{id}', [NotificationController::class, 'NotifSendStatus24']);
        Route::post('/notifications/send-status-3/{id}', [NotificationController::class, 'NotifSendStatus3']);
    });

    // Старые роуты (для обратной совместимости)
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);
});
