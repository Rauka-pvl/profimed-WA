<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Список приёмов
     */
    public function index(Request $request)
    {
        $user = $request->user();
        dump($user);
        $type = $request->query('type', 'upcoming'); // upcoming или past

        $query = Appointment::where('patient_id', $user->id)
            ->with(['patient', 'doctor']);

        if ($type === 'upcoming') {
            $query->where('date', '>=', now()->format('Y-m-d'))
                ->orderBy('date', 'asc');
        } else {
            $query->where('date', '<', now()->format('Y-m-d'))
                ->orderBy('date', 'desc');
        }

        $appointments = $query->get()->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'date_time' => $appointment->date_time,
                'doctor_name' => $appointment->doctor->full_name ?? 'Неизвестно',
                'doctor_specialty' => $appointment->doctor->specialty ?? null,
                'clinic' => $appointment->clinic->name ?? null,
                'room' => $appointment->room,
                'status' => $appointment->status,
                'comment' => $appointment->comment,
                'created_at' => $appointment->created_at,
                'confirmed_at' => $appointment->confirmed_at,
                'source' => $appointment->source,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    }

    /**
     * Детали приёма
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $appointment = Appointment::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['doctor', 'patient'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'date_time' => $appointment->date_time,
                'doctor_name' => $appointment->doctor->full_name ?? 'Неизвестно',
                'doctor_specialty' => $appointment->doctor->specialty ?? null,
                'clinic' => $appointment->clinic->name ?? null,
                'room' => $appointment->room,
                'status' => $appointment->status,
                'comment' => $appointment->comment,
                'created_at' => $appointment->created_at,
                'confirmed_at' => $appointment->confirmed_at,
                'source' => $appointment->source,
            ],
        ]);
    }

    /**
     * Отмена приёма
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $appointment = Appointment::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Проверяем, можно ли отменить
        if ($appointment->status === 'canceled') {
            return response()->json([
                'success' => false,
                'message' => 'Приём уже отменён',
            ], 400);
        }

        if ($appointment->date_time < now()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя отменить прошедший приём',
            ], 400);
        }

        // Отменяем приём
        $appointment->update([
            'status' => 'canceled',
            'cancel_reason' => $request->input('reason'),
            'canceled_at' => now(),
        ]);

        // Отправляем уведомление (если нужно)
        // event(new AppointmentCanceled($appointment));

        return response()->json([
            'success' => true,
            'message' => 'Приём отменён',
        ]);
    }

    /**
     * Перенос приёма
     */
    public function reschedule(Request $request, $id)
    {
        $request->validate([
            'new_date_time' => 'required|date|after:now',
        ]);

        $user = $request->user();

        $appointment = Appointment::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Проверяем, можно ли перенести
        if ($appointment->status === 'canceled') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя перенести отменённый приём',
            ], 400);
        }

        // Переносим приём
        $appointment->update([
            'date_time' => $request->new_date_time,
            'status' => 'rescheduled',
            'rescheduled_at' => now(),
        ]);

        // Отправляем уведомление
        // event(new AppointmentRescheduled($appointment));

        return response()->json([
            'success' => true,
            'message' => 'Приём перенесён',
            'data' => [
                'id' => $appointment->id,
                'date_time' => $appointment->date_time,
                'status' => $appointment->status,
            ],
        ]);
    }

    /**
     * Создание нового приёма
     */
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:users,id',
            'date_time' => 'required|date|after:now',
            'comment' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        $appointment = Appointment::create([
            'user_id' => $user->id,
            'doctor_id' => $request->doctor_id,
            'date_time' => $request->date_time,
            'comment' => $request->comment,
            'status' => 'pending',
            'source' => 'app',
        ]);

        // Отправляем уведомление
        // event(new AppointmentCreated($appointment));

        return response()->json([
            'success' => true,
            'message' => 'Приём создан',
            'data' => [
                'id' => $appointment->id,
                'date_time' => $appointment->date_time,
                'status' => $appointment->status,
            ],
        ], 201);
    }
}
