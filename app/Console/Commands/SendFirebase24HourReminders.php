<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\PatientDeviceToken;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendFirebase24HourReminders extends Command
{
    protected $signature = 'reminders-firebase-24h:send';
    protected $description = 'Отправка Firebase напоминаний о приёмах (за 24 часа)';

    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    public function handle()
    {
        $this->info('🚀 Начинаем отправку напоминаний...');

        // Напоминания за 24 часа
        $this->send24HourReminders();

        $this->info('✅ Отправка завершена!');
    }

    protected function send24HourReminders()
    {
        $this->info('📅 Проверка напоминаний за 24 часа...');

        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');

        $appointments = Appointment::with(['doctor', 'patient'])
            ->where('date', $tomorrow)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('reminder_24h_sent', false)
            ->whereHas('patient', function ($query) {
                $query->whereNotNull('phone');
            })
            ->get();

        $count = 0;
        foreach ($appointments as $appointment) {
            $patient = $appointment->patient;
            $doctor = $appointment->doctor;

            $deviceTokens = PatientDeviceToken::where('patient_id', $patient->id)
                ->pluck('device_token');

            if (count($deviceTokens) > 0) {
                foreach ($deviceTokens as $deviceToken) {
                    $this->firebaseService->sendNotification(
                        $deviceToken,
                        'PROFIMED - Напоминание о завтрашнем приёме!',
                        "Уважаемый {$patient->full_name}, у вас приём: {$doctor->name} {$appointment->date} {$appointment->time}"
                    );
                }
            }

            $appointment->update(['reminder_24h_sent' => true]);
            $count++;
            $this->info("  ✓ Отправлено: {$patient->full_name} - {$appointment->date} {$appointment->time}");
        }

        $this->info("📊 Отправлено напоминаний за 24ч: {$count}");
    }
}
