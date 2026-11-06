<?php

use App\Http\Controllers\P1_Usuarios\AuthController;
use App\Http\Controllers\P1_Usuarios\BitacoraController;
use App\Http\Controllers\P1_Usuarios\UsuarioController;
use App\Http\Controllers\P2_MedicosAgendas\AgendaController;
use App\Http\Controllers\P2_MedicosAgendas\BloqueoController;
use App\Http\Controllers\P2_MedicosAgendas\EspecialidadController;
use App\Http\Controllers\P2_MedicosAgendas\HorarioController;
use App\Http\Controllers\P2_MedicosAgendas\MedicoController;
use App\Http\Controllers\P3_Pacientes\HistoriaController;
use App\Http\Controllers\P3_Pacientes\HistorialController;
use App\Http\Controllers\P3_Pacientes\PacienteController;
use App\Http\Controllers\P4_Citas\CitaController;
use App\Http\Controllers\P4_Citas\CitaWizardController;
use App\Http\Controllers\P4_Citas\EsperaController;
use App\Http\Controllers\P5_Laboratorio\LaboratorioController;
use App\Http\Controllers\P6_Reportes\ReporteController;
use App\Http\Controllers\P7_Notificaciones\AvisoController;
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
Route::middleware(['auth', 'role:admin,recepcionista,medico,paciente,lab'])->group(function () {

    Route::get('/avisos', [AvisoController::class, 'index'])->name('avisos.index');
    Route::post('/avisos/{aviso}/leer', [AvisoController::class, 'leer'])->name('avisos.leer');

    Route::get('/historial', [HistorialController::class, 'index'])->name('historial.index');
    Route::get('/historia/{paciente}', [HistoriaController::class, 'show'])->name('historia.show');
    Route::post('/historia/{paciente}/atenciones', [HistoriaController::class, 'store'])->name('historia.store');
    Route::post('/atenciones/{atencion}/derivaciones', [HistoriaController::class, 'derivar'])->name('historia.derivar');
    Route::post('/citas/{cita}/confirmar', [CitaController::class, 'confirmar'])->middleware('role:paciente')->name('citas.confirmar');

    Route::get('/api/agenda/slots', [AgendaController::class, 'apiSlots'])->middleware('role:admin,recepcionista,medico,paciente')->name('api.agenda.slots');

    Route::resource('bloqueos', BloqueoController::class)->only(['index', 'store', 'destroy'])->middleware('role:admin,medico');

    // Dashboard Principal
    Route::get('/', function () {
        if (auth()->user()->isPaciente()) {
            return redirect()->route('historial.index');
        }
        if (auth()->user()->isMedico()) {
            return redirect()->route('citas.index');
        }
        if (auth()->user()->rol?->slug === 'lab') {
            return redirect()->route('laboratorio.ordenes.index');
        }
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
        // CU2: Cuentas, Roles y Permisos — Solo Admin puede gestionar todos los roles
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

    });

    // ─────────────────────────────────────────────────────────────────
    // Módulo de Pacientes y Tutores (CU7) — Rol: Admin / Recepcionista / Médico
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista'])->group(function () {
        Route::resource('pacientes', PacienteController::class)->except(['destroy']);
        Route::patch('/pacientes/{paciente}/toggle', [PacienteController::class, 'toggleEstado'])->name('pacientes.toggle');
        Route::post('/pacientes/{paciente}/responsables', [PacienteController::class, 'storeResponsable'])->name('pacientes.responsables.store');
        Route::delete('/pacientes/{paciente}/responsables/{responsable}', [PacienteController::class, 'destroyResponsable'])->name('pacientes.responsables.destroy');
    });

    // ─────────────────────────────────────────────────────────────────
    // Módulo de Citas Médicas y Fichas (CU8, CU9, CU10, CU11)
    // Admin / Recepcionista / Médico: acceso completo al tablero y control
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista,medico'])->group(function () {
        // CU10: Tablero de Citas y Control de Asistencia (solo personal clínico)
        Route::get('/citas', [CitaController::class, 'index'])->name('citas.index');
        Route::post('/citas/{cita}/checkin', [CitaController::class, 'checkin'])->name('citas.checkin');
        Route::post('/citas/{cita}/iniciar', [CitaController::class, 'iniciarAtencion'])->name('citas.iniciar');
        Route::post('/citas/{cita}/finalizar', [CitaController::class, 'finalizarAtencion'])->name('citas.finalizar');
        Route::post('/citas/{cita}/inasistencia', [CitaController::class, 'marcarInasistencia'])->name('citas.inasistencia');
    });

    // ─────────────────────────────────────────────────────────────────
    // CU8, CU9, CU11 — Acceso también para Paciente (PAC)
    // El paciente puede solicitar su propia ficha, verla, cancelar y reprogramar
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista,medico,paciente'])->group(function () {
        // CU8: Wizard de Solicitud de Ficha Médica y Asignación de Turnos
        Route::get('/citas/nueva', [CitaWizardController::class, 'create'])->middleware('role:admin,recepcionista,paciente')->name('citas.nueva');
        Route::post('/citas', [CitaWizardController::class, 'store'])->middleware('role:admin,recepcionista,paciente')->name('citas.store');

        // CU9: Comprobante de Ficha Médica
        Route::get('/citas/{cita}', [CitaController::class, 'show'])->name('citas.show');

        // CU11: Cancelación y Reprogramación (PAC solo puede cancelar/reprogramar sus propias citas)
        Route::post('/citas/{cita}/cancelar', [CitaController::class, 'cancelar'])->name('citas.cancelar');
        Route::get('/citas/{cita}/reprogramar', [CitaController::class, 'reprogramarView'])->name('citas.reprogramar');
        Route::post('/citas/{cita}/reprogramar', [CitaController::class, 'reprogramar'])->name('citas.reprogramar.post');

        // CU13: Historial de citas del paciente
        Route::get('/mis-citas', [CitaController::class, 'misCitas'])->name('citas.mis_citas');
    });

    // ─────────────────────────────────────────────────────────────────
    // CU15: Módulo de Laboratorio Clínico
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,lab'])->group(function () {
        Route::get('/laboratorio/ordenes', [LaboratorioController::class, 'indexOrdenes'])->name('laboratorio.ordenes.index');
        Route::get('/laboratorio/ordenes/{orden}/resultados/crear', [LaboratorioController::class, 'createResultado'])->name('laboratorio.resultados.create');
        Route::post('/laboratorio/ordenes/{orden}/resultados', [LaboratorioController::class, 'storeResultado'])->name('laboratorio.resultados.store');
        Route::post('/laboratorio/resultados/{resultado}/validar', [LaboratorioController::class, 'validarResultado'])->name('laboratorio.resultados.validar');
        Route::post('/laboratorio/resultados/{resultado}/publicar', [LaboratorioController::class, 'publicarResultado'])->name('laboratorio.resultados.publicar');
    });

    Route::middleware(['role:admin,medico'])->group(function () {
        Route::get('/pacientes/{paciente}/laboratorio/orden', [LaboratorioController::class, 'crearOrden'])->name('laboratorio.ordenes.create');
        Route::post('/pacientes/{paciente}/laboratorio/orden', [LaboratorioController::class, 'storeOrden'])->name('laboratorio.ordenes.store');
    });

    Route::middleware(['role:admin,medico,paciente,lab'])->group(function () {
        Route::get('/laboratorio/ordenes/{orden}', [LaboratorioController::class, 'showOrden'])->name('laboratorio.ordenes.show');
        Route::get('/laboratorio/resultados/{resultado}', [LaboratorioController::class, 'showResultado'])->name('laboratorio.resultados.show');
        Route::get('/laboratorio/resultados/{resultado}/descargar', [LaboratorioController::class, 'descargarPdf'])->name('laboratorio.resultados.descargar');
        Route::get('/mis-resultados', [LaboratorioController::class, 'misResultados'])->name('laboratorio.mis_resultados');
    });

    // ─────────────────────────────────────────────────────────────────
    // CU18: Módulo de Lista de Espera y Recuperación de Cupos
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista,paciente'])->group(function () {
        Route::get('/espera', [EsperaController::class, 'index'])->name('espera.index');
        Route::get('/espera/inscribir', [EsperaController::class, 'create'])->name('espera.create');
        Route::post('/espera', [EsperaController::class, 'store'])->name('espera.store');
        Route::delete('/espera/{espera}', [EsperaController::class, 'destroy'])->name('espera.destroy');
        Route::get('/ofertas/{oferta}', [EsperaController::class, 'showOferta'])->name('espera.ofertas.show');
        Route::post('/ofertas/{oferta}/aceptar', [EsperaController::class, 'aceptar'])->name('espera.ofertas.aceptar');
        Route::post('/ofertas/{oferta}/rechazar', [EsperaController::class, 'rechazar'])->name('espera.ofertas.rechazar');
    });

    // ─────────────────────────────────────────────────────────────────
    // CU12: Consulta de Agenda Médica y Turnos (Admin, Recepcionista, Médico)
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista,medico'])->group(function () {
        Route::get('/agenda', [AgendaController::class, 'show'])->name('agenda.show');

    });

    // ─────────────────────────────────────────────────────────────────
    // CU17: Módulo de Reportes Analíticos e Indicadores Clínicos con IA
    // ─────────────────────────────────────────────────────────────────
    Route::middleware(['role:admin,recepcionista'])->group(function () {
        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/exportar/csv', [ReporteController::class, 'exportarCsv'])->name('reportes.csv');
        Route::get('/reportes/exportar/pdf', [ReporteController::class, 'exportarPdf'])->name('reportes.pdf');
        Route::post('/reportes/ia/consultar', [ReporteController::class, 'consultarIa'])->name('reportes.ia.consultar');
    });

});
