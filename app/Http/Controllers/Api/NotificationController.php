<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientDeviceToken;
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
     * В будущем можно интегрировать с Firebase Cloud Messaging или другим сервисом
     */
    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
        ]);

        $patient = $request->user();
        $deviceTokens = $patient->deviceTokens()->get();

        if ($deviceTokens->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Нет зарегистрированных устройств для отправки уведомлений',
            ], 400);
        }

        // TODO: Здесь будет интеграция с FCM или другим push-сервисом
        // Пока просто логируем
        Log::info('Push notification request', [
            'patient_id' => $patient->id,
            'title' => $request->title,
            'body' => $request->body,
            'devices_count' => $deviceTokens->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Уведомление отправлено',
            'data' => [
                'title' => $request->title,
                'body' => $request->body,
                'devices_count' => $deviceTokens->count(),
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

