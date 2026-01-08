<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * Получение данных пациента для личного кабинета
     */
    public function profile(Request $request)
    {
        $patient = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $patient->id,
                'full_name' => $patient->full_name,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'middle_name' => $patient->middle_name,
                'phone' => $patient->phone,
                'date_of_birth' => $patient->date_of_birth,
                'role' => $patient->role,
                'created_at' => $patient->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $patient->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Обновление данных пациента
     */
    public function update(Request $request)
    {
        $patient = $request->user();

        $request->validate([
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
        ]);

        $patient->update($request->only([
            'full_name',
            'first_name',
            'last_name',
            'middle_name',
            'date_of_birth',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Данные обновлены',
            'data' => [
                'id' => $patient->id,
                'full_name' => $patient->full_name,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'middle_name' => $patient->middle_name,
                'phone' => $patient->phone,
                'date_of_birth' => $patient->date_of_birth,
            ],
        ]);
    }
}

