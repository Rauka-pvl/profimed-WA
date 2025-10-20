<?php

namespace App\Http\Controllers;

use App\Helpers\LogHelper;
use App\Models\Doctor;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index()
    {
        $doctors = Doctor::withCount('appointments')
            ->orderBy('name')
            ->paginate(20);

        return view('doctors.index', compact('doctors'));
    }

    public function create()
    {
        return view('doctors.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $doctor = Doctor::create($request->only('name'));

        LogHelper::userAction('Cоздание врача', [
            'model' => get_class($doctor),
            'model_id' => $doctor->id,
            'add' => $request->all(),
        ]);

        return redirect()->route('doctors.index')
            ->with('success', 'Врач успешно добавлен');
    }

    public function edit(Doctor $doctor)
    {
        return view('doctors.edit', compact('doctor'));
    }

    public function update(Request $request, Doctor $doctor)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $doctor->update($request->only('name'));

        LogHelper::userAction('Обновление врача', [
            'model' => get_class($doctor),
            'model_id' => $doctor->id,
            'changes' => $request->all(),
        ]);

        return redirect()->route('doctors.index')
            ->with('success', 'Врач успешно обновлён');
    }

    public function destroy(Doctor $doctor)
    {
        LogHelper::userAction('Удалён врач', [
            'doctor_id' => $doctor->id,
            'Doctor' => $doctor->name ?? null,
        ]);
        $doctor->delete();

        return redirect()->route('doctors.index')
            ->with('success', 'Врач удалён');
    }
}
