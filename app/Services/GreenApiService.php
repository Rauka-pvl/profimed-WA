<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GreenApiService
{
    protected $instanceId = '7105345781';
    protected $apiToken = 'a1608de02a014d3fa6483df1477fcf9f76b59e0c75654629a0';
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
                Log::info('Response: ' . $response->body());
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
    public function send24HourReminder(string $phone, string $patientName, string $doctorName, string $date, string $time, string $cabinet = null, string $service): bool
    {
        $cabinetText = $cabinet ? " {$cabinet} кабинетінде" : "";

        $message = "🏥 Сәлеметсіз бе, {$patientName}!\n";
        $message .= "Сізбен “PROFIMED” клиникасының автоматтандырылған хабарламалар жүйесі байланысуда.\n\n";
        $message .= "Қабылдауыңыз туралы еске саламыз:\n";
        $message .= "👨‍⚕️ Дәрігер: {$doctorName}\n";
        $message .= "📅 Күні: {$date}\n";
        $message .= "🕐 Уақыты: {$time}{$cabinetText}\n";
        $message .= "Қызмет: {$service}\n\n";
        $message .= "Жазылуды растау үшін — «1» деп жауап беріңіз.\n";
        $message .= "Қабылдаудан бас тарту үшін — «2» деп жауап беріңіз.\n";
        $message .= "Назар аударыңыз, бұл хабарлама автоматты түрде жіберіледі. Барлық сұрақтар бойынша келесі телефондарға хабарласыңыз:\n";
        $message .= "8 (7182) 76 40 86, 8 (778) 021 05 02, 8 (707) 621 21 21 — WhatsApp.\n";

        $message .= "💚 Күніңіз сәтті өтсін, құрметті пациент!\n";
        $message .= "Біздің клиника және қызметтер туралы толығырақ білу үшін instagram кіріңіз: https://www.instagram.com/profimed.mc\n";

        $message .= "——————————————————\n";

        $cabinetText = $cabinet ? " в кабинете {$cabinet}" : "";

        $message .= "🏥 Здравствуйте, {$patientName}!\n";
        $message .= "Вас беспокоит  автоматическая рассылка клиники “PROFIMED”\n\n";
        $message .= "Напоминаем о вашем приёме:\n";
        $message .= "👨‍⚕️ Врач: {$doctorName}\n";
        $message .= "📅 Дата: {$date}\n";
        $message .= "🕐 Время: {$time}{$cabinetText}\n";
        $message .= "Услуга: {$service}\n\n";
        $message .= "Для подтверждения записи - ответьте «1»\n";
        $message .= "Для отмены приема ответьте «2»\n\n";
        $message .= "Обращаем ваше внимание, что рассылка является автоматической.\n";
        $message .= "По всем вопросам просим обращаться по телефонам:";
        $message .= "8 (7182) 76 40 86, 8 (778) 021 05 02, 8 (707) 621 21 21 — WhatsApp.\n";

        $message .= "💚 Желаем вам отличного дня, наш дорогой пациент!\n";
        $message .= "Чтобы узнать больше о нашей клинике и услугах, посетите наш instagram https://www.instagram.com/profimed.mc\n";


        return $this->sendMessage($phone, $message);
    }

    /**
     * Отправка напоминания за 3 часа
     */
    public function send3HourReminder(string $phone, string $doctorName, string $time, string $cabinet = null): bool
    {
        $cabinetText = $cabinet ? " {$cabinet} кабинетінде" : "";

        $message = "⏰ Еске салу!\n\n";
        $message .= "Сіздің қабылдауыңыз бүгін, 3 сағаттан кейін:\n";
        $message .= "👨‍⚕️ Дәрігер: {$doctorName}\n";
        $message .= "🕐 Уақыты: {$time}{$cabinetText}\n\n";
        $message .= "Сізді PROFIMED клиникасында күтеміз! 🏥\n\n";

        $message .= "💚 Күніңіз сәтті өтсін, құрметті пациент!\n";
        $message .= "Біздің клиника және қызметтер туралы толығырақ білу үшін instagram кіріңіз: https://www.instagram.com/profimed.mc\n";

        $message .= "———————————————————————\n";

        $cabinetText = $cabinet ? " в кабинете {$cabinet}" : "";

        $message .= "⏰ Напоминание!\n\n";
        $message .= "Ваш приём сегодня через 3 часа:\n";
        $message .= "👨‍⚕️ Врач: {$doctorName}\n";
        $message .= "🕐 Время: {$time}{$cabinetText}\n\n";
        $message .= "Ждём вас в клинике PROFIMED! 🏥\n\n";

        $message .= "💚 Желаем вам отличного дня, наш дорогой пациент!\n";
        $message .= "Чтобы узнать больше о нашей клинике и услугах, посетите наш instagram https://www.instagram.com/profimed.mc\n";


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
