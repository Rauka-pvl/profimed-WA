<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class AppointmentObserver
{
    protected FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Обработка события создания новой записи
     */
    public function created(Appointment $appointment): void
    {
        // Отправляем уведомление только для записей, которые не отменены
        if ($appointment->status === 'cancelled') {
            Log::info('Пропущена отправка FCM уведомления: запись отменена', [
                'appointment_id' => $appointment->id,
            ]);
            return;
        }

        try {
            $this->fcmService->sendNewAppointmentNotification($appointment);
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем создание записи
            Log::error('Ошибка при отправке FCM уведомления о новой записи', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
