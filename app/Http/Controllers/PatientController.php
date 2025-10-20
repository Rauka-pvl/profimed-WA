<?php

namespace App\Http\Controllers;

use App\Helpers\LogHelper;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::withCount('appointments');

        // Поиск
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        $patients = $query->orderBy('full_name')->paginate(20);

        return view('patients.index', compact('patients'));
    }

    public function create()
    {
        return view('patients.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $patient = Patient::create($request->only(['full_name', 'phone']));

        LogHelper::userAction('Обновление пациента', [
            'patient' => get_class($patient),
            'patient_id' => $patient->id,
            'changes' => $request->all(),
        ]);

        return redirect()->route('patients.index')
            ->with('success', 'Пациент успешно добавлен');
    }

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, Patient $patient)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $patient->update($request->only(['full_name', 'phone']));

        LogHelper::userAction('Обновление пациента', [
            'patient' => get_class($patient),
            'patient_id' => $patient->id,
            'changes' => $request->all(),
        ]);

        return redirect()->route('patients.index')
            ->with('success', 'Пациент успешно обновлён');
    }

    public function destroy(Patient $patient)
    {
        LogHelper::userAction('Удалён пациент', [
            'patient_id' => $patient->id,
            'patient' => $patient->full_name ?? null,
        ]);
        $patient->delete();

        return redirect()->route('patients.index')
            ->with('success', 'Пациент удалён');
    }
}
