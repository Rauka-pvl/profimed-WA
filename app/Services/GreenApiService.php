<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GreenApiService
{
    protected $instanceId = '7105343963';
    protected $apiToken = '07e94e138d7e4c0a99534bf824010a031865571c09ed40de9d';
    protected $baseUrl = "https://7105.api.greenapi.com";

    /**
     * Отправка сообщения в WhatsApp
     */
    public function sendMessage(string $phone, string $message): bool
    {
        try {
            // Форматируем номер телефона для WhatsApp (без + и с @c.us)
            $chatId = $this->formatPhoneNumber($phone);

            $url = "{$this->baseUrl}/waInstance{$this->instanceId}/sendMessage/{$this->apiToken}";

            $response = Http::post($url, [
                'chatId' => $chatId,
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info("WhatsApp сообщение отправлено: {$phone}");
                return true;
            }

            Log::error("Ошибка отправки WhatsApp: {$response->body()}");
            return false;
        } catch (\Exception $e) {
            Log::error("Исключение при отправке WhatsApp: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Отправка напоминания за 24 часа
     */
    public function send24HourReminder(string $phone, string $doctorName, string $date, string $time, string $cabinet = null): bool
    {
        $cabinetText = $cabinet ? " в кабинете {$cabinet}" : "";

        $message = "🏥 Здравствуйте!\n\n";
        $message .= "Напоминаем о вашем приёме в клинике PROFIMED:\n";
        $message .= "👨‍⚕️ Врач: {$doctorName}\n";
        $message .= "📅 Дата: {$date}\n";
        $message .= "🕐 Время: {$time}{$cabinetText}\n\n";
        $message .= "Пожалуйста, подтвердите ваш приход, ответив:\n";
        $message .= "✅ ДА - если придёте\n";
        $message .= "❌ НЕТ - если не сможете прийти";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Отправка напоминания за 3 часа
     */
    public function send3HourReminder(string $phone, string $doctorName, string $time, string $cabinet = null): bool
    {
        $cabinetText = $cabinet ? " в кабинете {$cabinet}" : "";

        $message = "⏰ Напоминание!\n\n";
        $message .= "Ваш приём сегодня через 3 часа:\n";
        $message .= "👨‍⚕️ Врач: {$doctorName}\n";
        $message .= "🕐 Время: {$time}{$cabinetText}\n\n";
        $message .= "Ждём вас в клинике PROFIMED! 🏥";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Получение входящих сообщений (webhook обработка)
     */
    public function receiveNotifications(): array
    {
        try {
            $url = "{$this->baseUrl}/waInstance{$this->instanceId}/receiveNotification/{$this->apiToken}";

            $response = Http::get($url);

            Log::info('Получение уведомлений от Green API', ['response' => $response->body()]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error("Ошибка получения уведомлений: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Удаление уведомления после обработки
     */
    public function deleteNotification(int $receiptId): bool
    {
        try {
            $url = "{$this->baseUrl}/waInstance{$this->instanceId}/deleteNotification/{$this->apiToken}/{$receiptId}";

            $response = Http::delete($url);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Ошибка удаления уведомления: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Форматирование номера телефона для WhatsApp
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Убираем все символы кроме цифр
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Добавляем @c.us
        return $phone . '@c.us';
    }
}
