<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $stats = [
            'total_appointments' => Appointment::count(),
            'today_appointments' => Appointment::whereDate('date', $today)->count(),
            'week_appointments' => Appointment::whereBetween('date', [$weekStart, $weekEnd])->count(),
            'total_doctors' => Doctor::count(),
            'total_patients' => Patient::count(),
            'scheduled' => Appointment::where('status', 'scheduled')->count(),
            'confirmed' => Appointment::where('status', 'confirmed')->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->count(),
        ];

        // Ближайшие приёмы
        $upcomingAppointments = Appointment::with(['doctor', 'patient'])
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('time')
            ->limit(10)
            ->get();

        return view('dashboard', compact('stats', 'upcomingAppointments'));
    }
}
