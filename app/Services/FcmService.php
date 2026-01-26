<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FcmService
{
    protected string $fcmUrl;
    protected ?string $projectId = null;
    protected ?string $serviceAccountPath = null;
    protected ?array $serviceAccount = null;
    protected ?string $accessToken = null;
    protected ?Carbon $tokenExpiresAt = null;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        $this->serviceAccountPath = config('services.fcm.service_account_path');
        
        if ($this->serviceAccountPath && file_exists($this->serviceAccountPath)) {
            $this->serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
            $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        } else {
            // Fallback на старый API, если Service Account не настроен
            $this->fcmUrl = 'https://fcm.googleapis.com/fcm/send';
        }
    }

    /**
     * Отправка уведомления о новой записи
     */
    public function sendNewAppointmentNotification(Appointment $appointment): bool
    {
        // Загружаем связи, если они не загружены
        if (!$appointment->relationLoaded('doctor')) {
            $appointment->load('doctor');
        }
        if (!$appointment->relationLoaded('patient')) {
            $appointment->load('patient');
        }

        $patient = $appointment->patient;
        $doctor = $appointment->doctor;

        if (!$patient) {
            Log::warning('Не удалось отправить FCM уведомление: пациент не найден', [
                'appointment_id' => $appointment->id,
            ]);
            return false;
        }

        // Получаем все device tokens пациента
        $deviceTokens = $patient->deviceTokens()->pluck('device_token')->toArray();

        if (empty($deviceTokens)) {
            Log::info('FCM токены не найдены для пациента', [
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
            ]);
            return false;
        }

        $doctorName = $doctor ? $doctor->name : 'Врач';
        
        // Формируем уведомление
        $notification = [
            'title' => 'Новая запись',
            'body' => "У вас новая запись к врачу {$doctorName}",
        ];

        $data = [
            'type' => 'new_appointment',
            'appointment_id' => (string) $appointment->id,
        ];

        // Отправляем на все устройства пациента
        $successCount = 0;
        foreach ($deviceTokens as $deviceToken) {
            if ($this->sendToDevice($deviceToken, $notification, $data)) {
                $successCount++;
            }
        }

        Log::info('FCM уведомление о новой записи отправлено', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'devices_count' => count($deviceTokens),
            'success_count' => $successCount,
        ]);

        return $successCount > 0;
    }

    /**
     * Получение OAuth2 access token для Service Account
     */
    protected function getAccessToken(): ?string
    {
        // Если токен еще действителен, возвращаем его
        if ($this->accessToken && $this->tokenExpiresAt && $this->tokenExpiresAt->isFuture()) {
            return $this->accessToken;
        }

        if (!$this->serviceAccount) {
            Log::error('FCM Service Account не настроен');
            return null;
        }

        try {
            $now = Carbon::now();
            $exp = $now->addHour()->timestamp;

            // Создаем JWT для получения access token
            $jwt = $this->createJWT($exp);

            // Получаем access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'] ?? null;
                $this->tokenExpiresAt = Carbon::now()->addSeconds($data['expires_in'] ?? 3600);
                
                return $this->accessToken;
            } else {
                Log::error('Ошибка получения FCM access token', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Исключение при получении FCM access token', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Создание JWT токена для Service Account
     */
    protected function createJWT(int $exp): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $exp,
            'iat' => Carbon::now()->timestamp,
        ];

        $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

        $signature = '';
        $data = $base64UrlHeader . '.' . $base64UrlPayload;
        
        // Получаем приватный ключ и обрабатываем его
        $privateKey = $this->serviceAccount['private_key'];
        
        // Если ключ в формате с \n, конвертируем их в реальные переносы строк
        $privateKey = str_replace('\\n', "\n", $privateKey);
        
        // Если ключ не содержит BEGIN/END, добавляем их
        if (strpos($privateKey, '-----BEGIN') === false) {
            $privateKey = "-----BEGIN PRIVATE KEY-----\n" . $privateKey . "\n-----END PRIVATE KEY-----";
        }
        
        $keyResource = openssl_pkey_get_private($privateKey);
        
        if (!$keyResource) {
            throw new \Exception('Не удалось загрузить приватный ключ: ' . openssl_error_string());
        }
        
        openssl_sign($data, $signature, $keyResource, OPENSSL_ALGO_SHA256);
        openssl_free_key($keyResource);
        
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    /**
     * Base64 URL encoding
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Отправка уведомления на конкретное устройство
     */
    public function sendToDevice(string $deviceToken, array $notification, array $data): bool
    {
        // Если используется новый API (V1)
        if ($this->serviceAccount && $this->projectId) {
            return $this->sendToDeviceV1($deviceToken, $notification, $data);
        }

        // Fallback на старый API (если Service Account не настроен)
        Log::warning('Используется старый FCM API. Настройте Service Account для использования V1 API');
        return false;
    }

    /**
     * Отправка уведомления через FCM V1 API
     */
    protected function sendToDeviceV1(string $deviceToken, array $notification, array $data): bool
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            Log::error('Не удалось получить FCM access token');
            return false;
        }

        try {
            // Формируем сообщение для V1 API
            $message = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => $notification,
                    'data' => array_map('strval', $data), // Все значения должны быть строками
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $message);

            if ($response->successful()) {
                $responseData = $response->json();
                $messageId = $responseData['name'] ?? null;
                
                Log::info('FCM уведомление успешно отправлено (V1)', [
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                    'message_id' => $messageId,
                ]);
                return true;
            } else {
                Log::error('Ошибка отправки FCM уведомления (V1)', [
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Исключение при отправке FCM уведомления (V1)', [
                'device_token' => substr($deviceToken, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
