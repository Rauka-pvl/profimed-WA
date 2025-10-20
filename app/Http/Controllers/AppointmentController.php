<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Appointment::with(['doctor', 'patient']);

        // Поиск
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('patient', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })
                    ->orWhereHas('doctor', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Фильтр по дате
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        // Фильтр по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ✅ Сортировка
        $sortable = ['date', 'time', 'status']; // поля из appointments
        $sortField = $request->get('sort_by', 'date');
        $sortDir = $request->get('sort_dir', 'desc');

        if (!in_array($sortField, $sortable)) {
            // Проверяем поля через отношения
            if ($sortField === 'doctor') {
                $query->join('doctors', 'appointments.doctor_id', '=', 'doctors.id')
                    ->select('appointments.*', 'doctors.name as doctor_name')
                    ->orderBy('doctor_name', $sortDir);
            } elseif ($sortField === 'patient') {
                $query->join('patients', 'appointments.patient_id', '=', 'patients.id')
                    ->select('appointments.*', 'patients.full_name as patient_name')
                    ->orderBy('patient_name', $sortDir);
            } else {
                $query->orderBy('date', 'desc')->orderBy('time', 'desc');
            }
        } else {
            $query->orderBy($sortField, $sortDir);
        }

        $appointments = $query->paginate(20)->appends($request->query());

        return view('appointments.index', compact('appointments', 'sortField', 'sortDir'));
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['doctor', 'patient']);
        return view('appointments.show', compact('appointment'));
    }

    public function destroy(Appointment $appointment)
    {
        LogHelper::userAction('Удалён приём', [
            'appointment_id' => $appointment->id,
            'patient' => $appointment->patient->full_name ?? null,
        ]);
        $appointment->delete();
        return redirect()->route('appointments.index')
            ->with('success', 'Приём удалён');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $request->validate([
            'status' => 'required|in:scheduled,confirmed,cancelled',
        ]);

        $appointment->update(['status' => $request->status]);

        LogHelper::userAction('Обновление записии', [
            'model' => get_class($appointment),
            'model_id' => $appointment->id,
            'changes' => $request->all(),
        ]);

        return redirect()->back()
            ->with('success', 'Статус обновлён');
    }
}
