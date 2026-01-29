<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function sendCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^7\d{10}$/',
        ]);

        $phone = $request->phone;
        $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        cache()->put("sms_code_{$phone}", $code, now()->addMinutes(5));

        // Отправка SMS через Mobizon API
        $apiKey = 'kz091c4ce521d2ad9e4b85a8769df852b0266cb8c602c4316539e1611d4f6d04452819';
        $message = "eQabylau\nВаш код авторизации: {$code}\nКод действителен 5 минут.";

        $url = "https://api.mobizon.kz/service/message/sendsmsmessage";

        try {
            $response = Http::get($url, [
                'recipient' => $phone,
                'text' => $message,
                'apiKey' => $apiKey,
            ]);

            if ($response->successful()) {
                Log::info('SMS отправлено через Mobizon', [
                    'phone' => $phone,
                    'code' => $code,
                    'response' => $response->json(),
                ]);
            } else {
                Log::error('Ошибка отправки SMS через Mobizon', [
                    'phone' => $phone,
                    'code' => $code,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Исключение при отправке SMS', [
                'phone' => $phone,
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('sendCode', ['phone' => $phone, 'code' => $code]);

        return response()->json([
            'success' => true,
            'message' => 'Код отправлен',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^7\d{10}$/',
            'code' => 'required|string|size:4',
        ]);

        $phone = $request->phone;
        $code = $request->code;
        $cachedCode = cache()->get("sms_code_{$phone}");

        if (!$cachedCode || $cachedCode != $code) {
            throw ValidationException::withMessages(['code' => ['Неверный код']]);
        }

        cache()->forget("sms_code_{$phone}");

        $patient = Patient::firstOrCreate(
            ['phone' => '+' . $phone],
            ['full_name' => 'Test Patient']
        );

        $token = $patient->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'patient' => [
                'id' => $patient->id,
                'phone' => $patient->phone,
                'full_name' => $patient->full_name,
                // 'first_name' => $patient->first_name,
                // 'last_name' => $patient->last_name,
                // 'middle_name' => $patient->middle_name,
                // 'date_of_birth' => $patient->date_of_birth,
                // 'role' => $patient->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $patient = $request->user();

        // Если передан device_token — удаляем его из БД для этого пациента
        if ($request->filled('device_token')) {
            $request->validate([
                'device_token' => 'string',
                'device_type' => 'nullable|string',
            ]);

            $patient->deviceTokens()
                ->where('device_token', $request->device_token)
                ->delete();
        }

        $patient->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Вы вышли из системы']);
    }

    public function deviceToken(Request $request)
    {
        Log::info('Device Token', [$request->all()]);
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'required|string|in:ios,android',
        ]);

        $patient = $request->user();

        $patient->deviceTokens()->create(
            ['device_token' => $request->device_token, 'device_type' => $request->device_type]
        );

        return response()->json(['success' => true, 'message' => 'Токен обновлён']);
    }
}
