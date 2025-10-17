<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Services\GreenApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected $greenApi;

    public function __construct(GreenApiService $greenApi)
    {
        $this->greenApi = $greenApi;
    }

    /**
     * Обработка входящих сообщений от Green API
     */
    public function handleIncoming(Request $request)
    {
        // Log::info('Получение уведомлений от Green API', ['request' => $request->all()]);
        // return response()->json(['status' => 'received']);
        try {
            // Получаем уведомления
            $notifications = $request->all();

            if (empty($notifications)) {
                return response()->json(['status' => 'no notifications']);
            }

            // foreach ($notifications as $notification) {
            $this->processNotification($notifications);
            // }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Обработка отдельного уведомления
     */
    protected function processNotification(array $notification)
    {
        // Проверяем тип уведомления (только входящие сообщения)
        if (
            !isset($notification['typeWebhook']) ||
            $notification['typeWebhook'] !== 'incomingMessageReceived'
        ) {
            return;
        }

        $messageData = $notification['messageData'] ?? null;
        if (!$messageData) {
            return;
        }

        // Извлекаем номер телефона и текст сообщения
        $chatId = $notification['senderData']['chatId'] ?? null;
        $messageText = trim(mb_strtoupper($messageData['textMessageData']['textMessage'] ?? ''));

        if (!$chatId || !$messageText) {
            return;
        }

        $phone = $chatId;

        // Форматируем номер телефона (убираем @c.us)
        $phone = str_replace('@c.us', '', $chatId);

        // Ищем пациента по номеру телефона
        $patient = Patient::where('phone', $phone)->first();
        if (!$patient) {
            Log::info("Пациент не найден для номера: {$phone}");
            return;
        }

        // Ищем ближайший приём этого пациента
        $appointment = Appointment::where('patient_id', $patient->id)
            ->where('date', '>=', now()->format('Y-m-d'))
            ->where('status', 'scheduled')
            ->orderBy('date')
            ->orderBy('time')
            ->first();

        if (!$appointment) {
            Log::info("Приём не найден для пациента: {$patient->full_name}");
            return;
        }

        // Обрабатываем ответ ДА/НЕТ
        if (str_contains($messageText, 'ДА') || str_contains($messageText, 'ИӘ') || str_contains($messageText, 'YES')) {
            $appointment->update(['status' => 'confirmed']);
            Log::info("Приём подтверждён: {$patient->full_name} - {$appointment->date} {$appointment->time}");

            // Отправляем подтверждение
            $this->greenApi->sendMessage(
                $chatId,
                "✅ Спасибо! Ваш приём подтверждён. Ждём вас!\n✅ Рақмет! Сіздің қабылдауыңыз расталды. Сізді күтеміз!"
            );
        } elseif (str_contains($messageText, 'НЕТ') || str_contains($messageText, 'ЖОҚ') || str_contains($messageText, 'ЖОК') || str_contains($messageText, 'NO')) {
            $appointment->update(['status' => 'cancelled']);
            Log::info("Приём отменён: {$patient->full_name} - {$appointment->date} {$appointment->time}");

            // Отправляем подтверждение отмены
            $this->greenApi->sendMessage(
                $phone,
                "❌ Ваш приём отменён. При необходимости запишитесь на другое время. Спасибо!\n❌ Сіздің қабылдауыңыз тоқтатылды. Қажет болса, өзіңізге ыңғайлы басқа уақытқа жазылыңыз. Рақмет!"
            );
        }

        // Удаляем обработанное уведомление
        if (isset($notification['receiptId'])) {
            $this->greenApi->deleteNotification($notification['receiptId']);
        }
    }
}
