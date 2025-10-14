<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\GreenApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendWhatsappReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Отправка WhatsApp напоминаний о приёмах (за 24 часа и за 3 часа)';

    protected $greenApi;

    public function __construct(GreenApiService $greenApi)
    {
        parent::__construct();
        $this->greenApi = $greenApi;
    }

    public function handle()
    {
        $this->info('🚀 Начинаем отправку напоминаний...');

        // Напоминания за 24 часа
        $this->send24HourReminders();

        // Напоминания за 3 часа
        $this->send3HourReminders();

        $this->info('✅ Отправка завершена!');
    }

    protected function send24HourReminders()
    {
        $this->info('📅 Проверка напоминаний за 24 часа...');

        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');

        $appointments = Appointment::with(['doctor', 'patient'])
            ->where('date', $tomorrow)
            ->where('status', 'scheduled')
            ->where('reminder_24h_sent', false)
            ->whereHas('patient', function ($query) {
                $query->whereNotNull('phone');
            })
            ->get();

        $count = 0;
        foreach ($appointments as $appointment) {
            $patient = $appointment->patient;
            $doctor = $appointment->doctor;

            $dateFormatted = Carbon::parse($appointment->date)->format('d.m.Y');

            $success = $this->greenApi->send24HourReminder(
                $patient->phone,
                $doctor->name,
                $dateFormatted,
                $appointment->time,
                $appointment->cabinet
            );

            if ($success) {
                $appointment->update(['reminder_24h_sent' => true]);
                $count++;
                $this->info("  ✓ Отправлено: {$patient->full_name} ({$patient->phone})");
            } else {
                $this->error("  ✗ Ошибка: {$patient->full_name} ({$patient->phone})");
            }
        }

        $this->info("📊 Отправлено напоминаний за 24ч: {$count}");
    }

    protected function send3HourReminders()
    {
        $this->info('⏰ Проверка напоминаний за 3 часа...');

        $now = Carbon::now();
        $in3Hours = $now->copy()->addHours(3);

        $appointments = Appointment::with(['doctor', 'patient'])
            ->where('date', $now->format('Y-m-d'))
            ->where('status', 'scheduled')
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

                $success = $this->greenApi->send3HourReminder(
                    $patient->phone,
                    $doctor->name,
                    $appointment->time,
                    $appointment->cabinet
                );

                if ($success) {
                    $appointment->update(['reminder_3h_sent' => true]);
                    $count++;
                    $this->info("  ✓ Отправлено: {$patient->full_name} ({$patient->phone})");
                } else {
                    $this->error("  ✗ Ошибка: {$patient->full_name} ({$patient->phone})");
                }
            }
        }

        $this->info("📊 Отправлено напоминаний за 3ч: {$count}");
    }
}
