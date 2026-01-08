<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileAppointmentController extends Controller
{
    /**
     * Получение списка записей пациента
     */
    public function index(Request $request)
    {
        $patient = $request->user();
        Log::info('index', ['patient' => $patient]);
        $type = $request->query('type', 'upcoming'); // upcoming или past

        $query = Appointment::where('patient_id', $patient->id)
            ->with(['doctor']);

        if ($type === 'upcoming') {
            $query->where('date', '>=', now()->format('Y-m-d'))
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc');
        } else {
            $query->where('date', '<', now()->format('Y-m-d'))
                ->orderBy('date', 'desc')
                ->orderBy('time', 'desc');
        }

        $appointments = $query->get()->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'doctor' => [
                    'id' => $appointment->doctor->id ?? null,
                    'name' => $appointment->doctor->name ?? 'Неизвестно',
                ],
                'service' => $appointment->service ?? 'Услуга не указана',
                'cabinet' => $appointment->cabinet ?? 'Кабинет не указан',
                'date' => $appointment->date->format('Y-m-d'),
                'time' => $appointment->time,
                'status' => $appointment->status,
                'created_at' => $appointment->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $appointment->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    }

    /**
     * Получение детальной информации о записи
     */
    public function show(Request $request, $id)
    {
        $patient = $request->user();

        $appointment = Appointment::where('id', $id)
            ->where('patient_id', $patient->id)
            ->with(['doctor'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'doctor' => [
                    'id' => $appointment->doctor->id ?? null,
                    'name' => $appointment->doctor->name ?? 'Неизвестно',
                ],
                'service' => $appointment->service ?? 'Услуга не указана',
                'cabinet' => $appointment->cabinet ?? 'Кабинет не указан',
                'date' => $appointment->date->format('Y-m-d'),
                'time' => $appointment->time,
                'status' => $appointment->status,
                'reminder_24h_sent' => $appointment->reminder_24h_sent,
                'reminder_3h_sent' => $appointment->reminder_3h_sent,
                'created_at' => $appointment->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $appointment->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Подтверждение записи
     */
    public function confirm(Request $request, $id)
    {
        $patient = $request->user();

        $appointment = Appointment::where('id', $id)
            ->where('patient_id', $patient->id)
            ->firstOrFail();

        // Проверяем, можно ли подтвердить
        if ($appointment->status === 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Запись уже подтверждена',
            ], 400);
        }

        if ($appointment->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя подтвердить отменённую запись',
            ], 400);
        }

        if ($appointment->date->format('Y-m-d') < now()->format('Y-m-d')) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя подтвердить прошедшую запись',
            ], 400);
        }

        // Подтверждаем запись
        $appointment->update([
            'status' => 'confirmed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Запись подтверждена',
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
            ],
        ]);
    }

    /**
     * Отказ от записи
     */
    public function decline(Request $request, $id)
    {
        $patient = $request->user();

        $appointment = Appointment::where('id', $id)
            ->where('patient_id', $patient->id)
            ->firstOrFail();

        // Проверяем, можно ли отменить
        if ($appointment->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Запись уже отменена',
            ], 400);
        }

        if ($appointment->date->format('Y-m-d') < now()->format('Y-m-d')) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя отменить прошедшую запись',
            ], 400);
        }

        // Отменяем запись
        $appointment->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Запись отменена',
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
            ],
        ]);
    }
}
