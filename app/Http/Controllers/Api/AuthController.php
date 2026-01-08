<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
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
        $code = rand(1000, 9999);
        cache()->put("sms_code_{$phone}", $code, now()->addMinutes(5));

        Log::info('sendCode', ['phone' => $phone, 'code' => $code]);

        return response()->json(['success' => true, 'message' => 'Код отправлен', 'code' => $code]);
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
            ['phone' => '+7' . $phone],
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

    public function updateDeviceToken(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'required|in:ios,android',
        ]);

        $patient = $request->user();

        $patient->deviceTokens()->updateOrCreate(
            ['device_token' => $request->device_token],
            ['device_type' => $request->device_type, 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'message' => 'Токен обновлён']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Вы вышли из системы']);
    }
}
