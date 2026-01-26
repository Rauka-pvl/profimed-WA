<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Services\FcmService;
use Illuminate\Console\Command;

class TestFcmNotification extends Command
{
    protected $signature = 'fcm:test {patient_id? : ID пациента (если не указан, будет выбран первый с токенами)}';
    protected $description = 'Отправка тестового FCM уведомления на устройства пациента';

    public function handle()
    {
        $patientId = $this->argument('patient_id');
        
        if ($patientId) {
            $patient = Patient::find($patientId);
            if (!$patient) {
                $this->error("Пациент с ID {$patientId} не найден");
                return 1;
            }
        } else {
            // Ищем первого пациента с токенами
            $patient = Patient::whereHas('deviceTokens')->first();
            if (!$patient) {
                $this->error('Не найдено ни одного пациента с зарегистрированными устройствами');
                $this->info('Сначала зарегистрируйте устройство через мобильное приложение');
                return 1;
            }
        }

        $deviceTokens = $patient->deviceTokens()->pluck('device_token')->toArray();
        
        if (empty($deviceTokens)) {
            $this->error("У пациента ID {$patient->id} нет зарегистрированных устройств");
            return 1;
        }

        $this->info("Найдено устройств: " . count($deviceTokens));
        $this->info("Пациент: {$patient->full_name} (ID: {$patient->id})");

        $fcmService = app(FcmService::class);
        
        $notification = [
            'title' => 'Тестовое уведомление',
            'body' => 'Это тестовое уведомление от сервера Profimed. Если вы видите это сообщение, значит FCM работает правильно! 🎉',
        ];

        $data = [
            'type' => 'test_notification',
            'timestamp' => (string) now()->timestamp,
        ];

        $this->info('Отправка уведомления...');
        
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($deviceTokens as $index => $deviceToken) {
            $this->line("Устройство " . ($index + 1) . ": " . substr($deviceToken, 0, 20) . "...");
            
            if ($fcmService->sendToDevice($deviceToken, $notification, $data)) {
                $this->info("  ✅ Успешно отправлено");
                $successCount++;
            } else {
                $this->error("  ❌ Ошибка отправки");
                $failedCount++;
            }
        }

        $this->newLine();
        $this->info("Результат:");
        $this->info("  Успешно: {$successCount}");
        $this->info("  Ошибок: {$failedCount}");

        if ($successCount > 0) {
            $this->info("✅ Уведомление отправлено! Проверьте ваше устройство.");
        } else {
            $this->error("❌ Не удалось отправить уведомление. Проверьте логи и настройки FCM.");
        }

        return 0;
    }
}
