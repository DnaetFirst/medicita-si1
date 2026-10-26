<?php

use App\Http\Controllers\P1_Usuarios\AuthController;
use App\Http\Controllers\P1_Usuarios\BitacoraController;
use App\Http\Controllers\P1_Usuarios\UsuarioController;
use App\Http\Controllers\P2_MedicosAgendas\AgendaController;
use App\Http\Controllers\P2_MedicosAgendas\BloqueoController;
use App\Http\Controllers\P2_MedicosAgendas\EspecialidadController;
use App\Http\Controllers\P2_MedicosAgendas\HorarioController;
use App\Http\Controllers\P2_MedicosAgendas\MedicoController;
use App\Http\Controllers\P3_Pacientes\PacienteController;
use App\Http\Controllers\P4_Citas\CitaController;
use App\Http\Controllers\P4_Citas\CitaWizardController;
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
Route::middleware(['auth', 'role:admin,recepcionista,medico,paciente'])->group(function () {

    // Dashboard Principal
    Route::get('/', function () {
        $stats = [
            'citas_hoy' => Cita::whereDate('fecha', today())->count(),
            'medicos_activos' => Medico::where('estado', 'activo')->count(),
            'especialidades' => Especialidad::where('activo', true)->count(),
            'pacientes' => Paciente::where('activo', true)->count(),
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
    // Módulo de Médicos, Especialidades y Agendas (CU3, CU4, CU5, CU6) — Rol: Admin / Recepcionista
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista'])->group(function () {
        // CU3: Especialidades Médicas
        Route::resource('especialidades', EspecialidadController::class)->parameters(['especialidades' => 'especialidad'])->except(['show', 'create', 'edit']);
        Route::patch('/especialidades/{especialidad}/toggle', [EspecialidadController::class, 'toggleEstado'])->name('especialidades.toggle');

        // CU4: Gestión de Médicos
        Route::resource('medicos', MedicoController::class)->except(['destroy']);
        Route::patch('/medicos/{medico}/toggle', [MedicoController::class, 'toggleEstado'])->name('medicos.toggle');

        // CU5: Horarios de Atención y Generación de Slots
        Route::get('/horarios', [HorarioController::class, 'index'])->name('horarios.index');
        Route::get('/horarios/{medico}/edit', [HorarioController::class, 'edit'])->name('horarios.edit');
        Route::put('/horarios/{medico}', [HorarioController::class, 'update'])->name('horarios.update');
        Route::post('/horarios/{medico}/generar', [HorarioController::class, 'generarSlots'])->name('horarios.generar');

        // CU6: Bloqueos de Agenda y Postergaciones
        Route::resource('bloqueos', BloqueoController::class)->only(['index', 'store', 'destroy']);
    });

    // ─────────────────────────────────────────────────────────────────
    // Módulo de Pacientes y Tutores (CU7) — Rol: Admin / Recepcionista / Médico
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista,medico'])->group(function () {
        Route::resource('pacientes', PacienteController::class)->except(['destroy']);
        Route::patch('/pacientes/{paciente}/toggle', [PacienteController::class, 'toggleEstado'])->name('pacientes.toggle');
        Route::post('/pacientes/{paciente}/responsables', [PacienteController::class, 'storeResponsable'])->name('pacientes.responsables.store');
        Route::delete('/pacientes/{paciente}/responsables/{responsable}', [PacienteController::class, 'destroyResponsable'])->name('pacientes.responsables.destroy');
    });

    // ─────────────────────────────────────────────────────────────────
    // Módulo de Citas Médicas y Fichas (CU8, CU9, CU10, CU11) — Rol: Admin / Recepcionista / Médico
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista,medico'])->group(function () {
        // CU8: Wizard de Solicitud de Ficha Médica y Asignación de Turnos
        Route::get('/citas/nueva', [CitaWizardController::class, 'create'])->name('citas.nueva');
        Route::post('/citas', [CitaWizardController::class, 'store'])->name('citas.store');

        // CU10: Tablero de Citas y Control de Asistencia
        Route::get('/citas', [CitaController::class, 'index'])->name('citas.index');
        Route::post('/citas/{cita}/checkin', [CitaController::class, 'checkin'])->name('citas.checkin');
        Route::post('/citas/{cita}/iniciar', [CitaController::class, 'iniciarAtencion'])->name('citas.iniciar');
        Route::post('/citas/{cita}/finalizar', [CitaController::class, 'finalizarAtencion'])->name('citas.finalizar');
        Route::post('/citas/{cita}/inasistencia', [CitaController::class, 'marcarInasistencia'])->name('citas.inasistencia');

        // CU11: Cancelación y Reprogramación de Turnos
        Route::post('/citas/{cita}/cancelar', [CitaController::class, 'cancelar'])->name('citas.cancelar');
        Route::get('/citas/{cita}/reprogramar', [CitaController::class, 'reprogramarView'])->name('citas.reprogramar');
        Route::post('/citas/{cita}/reprogramar', [CitaController::class, 'reprogramar'])->name('citas.reprogramar.post');

        // CU9: Comprobante de Ficha Médica
        Route::get('/citas/{cita}', [CitaController::class, 'show'])->name('citas.show');
    });

    // ─────────────────────────────────────────────────────────────────
    // CU12: Consulta de Agenda Médica y Turnos (Admin, Recepcionista, Médico)
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista,medico'])->group(function () {
        Route::get('/agenda', [AgendaController::class, 'show'])->name('agenda.show');
        Route::get('/api/agenda/slots', [AgendaController::class, 'apiSlots'])->name('api.agenda.slots');
    });

});
