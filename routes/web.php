<?php

use App\Http\Controllers\P1_Usuarios\AuthController;
use App\Http\Controllers\P1_Usuarios\BitacoraController;
use App\Http\Controllers\P1_Usuarios\UsuarioController;
use App\Http\Controllers\P2_MedicosAgendas\EspecialidadController;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Públicas / Autenticación (CU1)
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Rutas Protegidas por Autenticación
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Dashboard Principal
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

    // ─────────────────────────────────────────────────────────────────
    // Módulos de Administración (CU2, CU19) — Rol: Administrador
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin'])->group(function () {
        // CU2: Cuentas, Roles y Permisos
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
        Route::patch('/usuarios/{usuario}/toggle', [UsuarioController::class, 'toggleEstado'])->name('usuarios.toggle');

        // CU19: Bitácora de Auditoría
        Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');
        Route::get('/bitacora/exportar', [BitacoraController::class, 'exportCsv'])->name('bitacora.export');
    });

    // ─────────────────────────────────────────────────────────────────
    // Módulo de Especialidades Médicas (CU3) — Rol: Admin / Recepcionista
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista'])->group(function () {
        Route::resource('especialidades', EspecialidadController::class)->except(['show', 'create', 'edit']);
        Route::patch('/especialidades/{especialidad}/toggle', [EspecialidadController::class, 'toggleEstado'])->name('especialidades.toggle');
    });

});
