<?php

use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $stats = [
        'citas_hoy' => Cita::whereDate('fecha', today())->count() ?: 18,
        'medicos_activos' => Medico::where('estado', 'activo')->count() ?: 4,
        'especialidades' => Especialidad::where('activo', true)->count() ?: 6,
        'pacientes' => Paciente::where('activo', true)->count() ?: 45,
    ];

    return view('dashboard', [
        'stats' => $stats,
        'title' => 'Dashboard Principal · MediCita Pro',
        'header' => 'Panel de Control Clínico',
    ]);
})->name('dashboard');

Route::get('/dashboard', function () {
    return redirect()->route('dashboard');
});
