<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\PatientDeviceToken;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendFirebase3HourReminders extends Command
{
    protected $signature = 'reminders-firebase-3h:send';
    protected $description = 'Отправка Firebase напоминаний о приёмах (за 3 часа)';

    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    public function handle()
    {
        $this->info('🚀 Начинаем отправку напоминаний...');

        // Напоминания за 3 часа
        $this->send3HourReminders();

        $this->info('✅ Отправка завершена!');
    }

    protected function send3HourReminders()
    {
        $this->info('⏰ Проверка напоминаний за 3 часа...');

        $now = Carbon::now();
        $in3Hours = $now->copy()->addHours(3);

        $appointments = Appointment::with(['doctor', 'patient'])
            ->where('date', $now->format('Y-m-d'))
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('reminder_3h_sent', false)
            ->whereHas('patient', function ($query) {
                $query->whereNotNull('phone');
            })
            ->get();

        $count = 0;
        foreach ($appointments as $appointment) {
            // Проверяем, что приём через 3 часа (±15 минут)
            $appointmentTime = Carbon::parse(explode(' ', $appointment->date)[0] . ' ' . explode(' - ', $appointment->time)[0]);
            $diffInMinutes = $now->diffInMinutes($appointmentTime, false);

            // Отправляем если осталось от 165 до 195 минут (3 часа ± 15 минут)
            if ($diffInMinutes >= 165 && $diffInMinutes <= 195) {
                $patient = $appointment->patient;
                $doctor = $appointment->doctor;

                $deviceTokens = PatientDeviceToken::where('patient_id', $patient->id)
                    ->pluck('device_token');

                if (count($deviceTokens) > 0) {
                    foreach ($deviceTokens as $deviceToken) {
                        $this->firebaseService->sendNotification(
                            $deviceToken,
                            'PROFIMED - Напоминание о приёме через 3 часа!',
                            "Уважаемый {$patient->full_name}, у вас приём: {$doctor->name} {$appointment->date} {$appointment->time}"
                        );
                    }
                }

                $appointment->update(['reminder_3h_sent' => true]);
                $count++;
                $this->info("  ✓ Отправлено: {$patient->full_name} - {$appointment->date} {$appointment->time}");
            }
        }

        $this->info("📊 Отправлено напоминаний за 3ч: {$count}");
    }
}
