<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Отправка SMS кода
     */
    public function sendCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^7\d{9}$/',
        ]);

        $phone = $request->phone;

        // Генерируем 4-значный код
        $code = rand(1000, 9999);

        // Сохраняем код в кэш на 5 минут
        cache()->put("sms_code_{$phone}", $code, now()->addMinutes(5));

        // Отправляем SMS через вашу SMS службу
        // Пример: SmsService::send($phone, "Ваш код: {$code}");

        // В режиме разработки можно вернуть код в ответе
        return response()->json([
            'success' => true,
            'message' => 'Код отправлен',
            'code' => $code, // Только для тестирования!
        ]);
    }

    /**
     * Проверка кода и авторизация
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^77\d{9}$/',
            'code' => 'required|string|size:4',
        ]);

        $phone = $request->phone;
        $code = $request->code;

        // Проверяем код из кэша
        $cachedCode = cache()->get("sms_code_{$phone}");

        if (!$cachedCode || $cachedCode != $code) {
            throw ValidationException::withMessages([
                'code' => ['Неверный код'],
            ]);
        }

        // Удаляем использованный код
        cache()->forget("sms_code_{$phone}");

        // Ищем или создаём пользователя
        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'full_name' => 'Test User'
            ]
        );

        // Создаём токен
        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'middle_name' => $user->middle_name,
                'date_of_birth' => $user->date_of_birth,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Обновление токена устройства (для push-уведомлений)
     */
    public function updateDeviceToken(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'required|in:ios,android',
        ]);

        $user = $request->user();

        // Сохраняем или обновляем токен устройства
        $user->deviceTokens()->updateOrCreate(
            [
                'device_token' => $request->device_token,
            ],
            [
                'device_type' => $request->device_type,
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Токен обновлён',
        ]);
    }

    /**
     * Выход
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Вы вышли из системы',
        ]);
    }
}
