<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Получение списка уведомлений (можно расширить в будущем)
     * Уведомления теперь отправляются локально с мобильного приложения
     */
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'notifications_enabled' => true,
                'message' => 'Уведомления отправляются локально с мобильного приложения',
            ],
        ]);
    }

    /**
     * Отправка тестового уведомления
     * Уведомления теперь отправляются локально с мобильного приложения
     */
    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
        ]);

        // Уведомления теперь отправляются локально с мобильного приложения
        return response()->json([
            'success' => true,
            'message' => 'Уведомления отправляются локально с мобильного приложения',
            'data' => [
                'title' => $request->title,
                'body' => $request->body,
            ],
        ]);
    }

    /**
     * Получение настроек уведомлений
     */
    public function settings(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'notifications_enabled' => true,
                'message' => 'Уведомления отправляются локально с мобильного приложения',
            ],
        ]);
    }
}

