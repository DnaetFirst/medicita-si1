@extends('layouts.app')

@section('content')
<div style="margin-bottom: 1.5rem;">
    <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
        Panel de Control Clínico
    </h1>
    <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
        Sistema Integrado de Gestión de Citas Médicas · Ciclo 1 (Etapa 1 Operativa)
    </p>
</div>

<!-- KPIs Grid -->
<div class="grid-4">
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
            <span style="font-size: var(--font-xs); font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Citas Hoy</span>
            <span class="badge badge-info">Programadas</span>
        </div>
        <div style="font-size: var(--font-3xl); font-weight: 700; color: var(--color-primary);">{{ $stats['citas_hoy'] ?? 24 }}</div>
        <div style="font-size: var(--font-xs); color: var(--color-success); margin-top: 0.25rem;">↑ 12% vs. semana anterior</div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
            <span style="font-size: var(--font-xs); font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Médicos en Turno</span>
            <span class="badge badge-success">Activos</span>
        </div>
        <div style="font-size: var(--font-3xl); font-weight: 700; color: var(--color-secondary);">{{ $stats['medicos_activos'] ?? 8 }}</div>
        <div style="font-size: var(--font-xs); color: var(--color-text-muted); margin-top: 0.25rem;">Horario: 08:00 - 17:00 (1h descanso)</div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
            <span style="font-size: var(--font-xs); font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Especialidades</span>
            <span class="badge badge-info">P2</span>
        </div>
        <div style="font-size: var(--font-3xl); font-weight: 700; color: var(--color-primary);">{{ $stats['especialidades'] ?? 6 }}</div>
        <div style="font-size: var(--font-xs); color: var(--color-text-muted); margin-top: 0.25rem;">Medicina, Pediatría, Odontología...</div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
            <span style="font-size: var(--font-xs); font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Pacientes Registrados</span>
            <span class="badge badge-warning">P3</span>
        </div>
        <div style="font-size: var(--font-3xl); font-weight: 700; color: var(--color-highlight);">{{ $stats['pacientes'] ?? 142 }}</div>
        <div style="font-size: var(--font-xs); color: var(--color-text-muted); margin-top: 0.25rem;">Con responsable autorizado</div>
    </div>
</div>

<!-- Quick Actions & Status -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="card">
        <h2 style="font-size: var(--font-lg); font-weight: 700; color: var(--color-primary); margin-bottom: 1rem;">
            Estado de Implementación — Ciclo 1 (Etapa 1 Completada)
        </h2>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: var(--color-bg); border-radius: var(--border-radius-sm);">
                <div>
                    <strong>Base de Datos y Modelos Relacionales</strong>
                    <div style="font-size: var(--font-xs); color: var(--color-text-muted);">10 migraciones y seeders ejecutados (Roles, Médicos, Especialidades, Pacientes, Citas, Bitácora)</div>
                </div>
                <span class="badge badge-success">Completado 100%</span>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: var(--color-bg); border-radius: var(--border-radius-sm);">
                <div>
                    <strong>Control de Versiones y Pipeline CI/CD</strong>
                    <div style="font-size: var(--font-xs); color: var(--color-text-muted);">Git inicializado con main/develop, render.yaml para Render.com y GitHub Actions</div>
                </div>
                <span class="badge badge-success">Completado 100%</span>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: var(--color-bg); border-radius: var(--border-radius-sm);">
                <div>
                    <strong>Diseño Responsivo "Medical Blue"</strong>
                    <div style="font-size: var(--font-xs); color: var(--color-text-muted);">Paleta oficial de colores, tokens CSS y navegación sidebar tipo drawer para móviles</div>
                </div>
                <span class="badge badge-success">Completado 100%</span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size: var(--font-lg); font-weight: 700; color: var(--color-primary); margin-bottom: 1rem;">
            Accesos Rápidos
        </h2>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="/citas/nueva" class="btn btn-primary" style="width: 100%;">
                + Agendar Cita (Wizard CU8)
            </a>
            <a href="/especialidades" class="btn btn-secondary" style="width: 100%;">
                Ver Especialidades (CU3)
            </a>
            <a href="/medicos" class="btn btn-secondary" style="width: 100%;">
                Ver Médicos & Horarios (CU4)
            </a>
            <a href="/pacientes" class="btn btn-secondary" style="width: 100%;">
                Registro de Pacientes (CU7)
            </a>
        </div>
    </div>
</div>
@endsection
