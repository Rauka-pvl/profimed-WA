<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientDeviceToken;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Получение списка уведомлений (можно расширить в будущем)
     * Пока возвращаем базовую информацию о настройках уведомлений
     */
    public function index(Request $request)
    {
        $patient = $request->user();

        // Получаем все токены устройства пациента
        $deviceTokens = $patient->deviceTokens()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications_enabled' => $deviceTokens->count() > 0,
                'devices' => $deviceTokens->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'device_type' => $token->device_type,
                        'created_at' => $token->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Отправка тестового уведомления
     * Отправляет FCM push-уведомление на все устройства текущего пользователя
     */
    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
        ]);

        $patient = $request->user();
        $deviceTokens = $patient->deviceTokens()->pluck('device_token')->toArray();

        if (empty($deviceTokens)) {
            return response()->json([
                'success' => false,
                'message' => 'Нет зарегистрированных устройств для отправки уведомлений',
            ], 400);
        }

        $fcmService = app(FcmService::class);
        
        $notification = [
            'title' => $request->title,
            'body' => $request->body,
        ];

        // Опциональные данные для уведомления
        $data = [
            'type' => 'test_notification',
            'timestamp' => (string) now()->timestamp,
        ];

        // Отправляем на все устройства пациента
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($deviceTokens as $deviceToken) {
            if ($fcmService->sendToDevice($deviceToken, $notification, $data)) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        Log::info('Тестовое FCM уведомление отправлено', [
            'patient_id' => $patient->id,
            'title' => $request->title,
            'body' => $request->body,
            'devices_count' => count($deviceTokens),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);

        return response()->json([
            'success' => $successCount > 0,
            'message' => $successCount > 0 
                ? "Уведомление отправлено на {$successCount} устройств" 
                : 'Не удалось отправить уведомление',
            'data' => [
                'title' => $request->title,
                'body' => $request->body,
                'devices_count' => count($deviceTokens),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ],
        ]);
    }

    /**
     * Получение настроек уведомлений
     */
    public function settings(Request $request)
    {
        $patient = $request->user();
        $deviceTokens = $patient->deviceTokens()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications_enabled' => $deviceTokens->count() > 0,
                'devices_count' => $deviceTokens->count(),
            ],
        ]);
    }
}

