@extends('layouts.app')

@section('content')
<div style="max-width: 820px; margin: 0 auto;">
    <!-- Acciones Superiores (Ocultas al imprimir) -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin: 0;">
                Comprobante de Ficha Médica (CU9)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin: 0.25rem 0 0 0;">
                Código Oficial de Cita: <strong>{{ $cita->codigo_ficha }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                Imprimir Comprobante
            </button>

            @if($cita->estado === 'programada')
                <form method="POST" action="{{ route('citas.checkin', $cita) }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Registrar Check-in (Recepción)
                    </button>
                </form>
            @endif

            <a href="{{ route('citas.index') }}" class="btn btn-secondary">
                Volver al Tablero
            </a>
        </div>
    </div>

    <!-- Comprobante Imprimible -->
    <div class="card printable-card" style="padding: 2.5rem; border: 2px solid var(--color-border); box-shadow: 0 10px 25px rgba(0,0,0,0.05); position: relative; overflow: hidden;">
        <!-- Encabezado Clínico -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 1.5rem; border-bottom: 2px solid var(--color-primary); margin-bottom: 1.5rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--color-primary); font-size: 1.5rem; font-weight: 800;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                    </svg>
                    Medi<span>Cita</span> Pro
                </div>
                <div style="font-size: 0.85rem; color: var(--color-text-muted); margin-top: 0.25rem;">
                    Centro Médico de Especialidades &bull; UAGRM - SI1
                </div>
                <div style="font-size: 0.8rem; color: var(--color-text-muted);">
                    Santa Cruz de la Sierra, Bolivia
                </div>
            </div>

            <div style="text-align: right;">
                <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">COMPROBANTE DE RESERVA</div>
                <div style="font-family: monospace; font-size: 1.35rem; font-weight: 800; color: var(--color-primary); letter-spacing: 0.5px;">
                    {{ $cita->codigo_ficha }}
                </div>
                <div style="margin-top: 0.25rem;">
                    @php
                        $badgeClass = match($cita->estado) {
                            'programada' => 'badge-info',
                            'confirmada' => 'badge-primary',
                            'en_curso' => 'badge-warning',
                            'atendida' => 'badge-success',
                            'cancelada' => 'badge-danger',
                            'no_asistio' => 'badge-danger',
                            default => 'badge-secondary',
                        };
                        $estadoLabel = match($cita->estado) {
                            'programada' => 'PROGRAMADA (PENDIENTE CHECK-IN)',
                            'confirmada' => 'CONFIRMADA EN RECEPCIÓN',
                            'en_curso' => 'EN CONSULTA MÉDICA',
                            'atendida' => 'ATENCIÓN CONCLUIDA',
                            'cancelada' => 'CITA CANCELADA',
                            'no_asistio' => 'NO ASISTIÓ',
                            default => strtoupper($cita->estado),
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}" style="font-weight: 700; font-size: 0.75rem;">
                        {{ $estadoLabel }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Alerta de Cancelación si aplica -->
        @if($cita->estado === 'cancelada')
            <div style="background: #fee2e2; border-left: 4px solid var(--color-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                <div style="font-weight: 700; color: #991b1b; margin-bottom: 0.25rem;">Esta cita médica fue cancelada</div>
                <div style="font-size: 0.85rem; color: #991b1b;">
                    <strong>Motivo:</strong> {{ $cita->motivo_cancelacion }}
                </div>
                @if($cita->canceladoPor)
                    <div style="font-size: 0.75rem; color: #991b1b; margin-top: 0.25rem;">
                        Registrado por: {{ $cita->canceladoPor->name }}
                    </div>
                @endif
            </div>
        @endif

        <!-- Cuerpo del Comprobante (Detalles del Turno y Partes) -->
        <div class="grid-2" style="gap: 2rem; margin-bottom: 2rem;">
            <!-- Datos del Paciente -->
            <div>
                <h4 style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); margin: 0 0 0.75rem 0; letter-spacing: 0.5px;">
                    1. DATOS DEL PACIENTE
                </h4>
                <div style="font-size: 0.95rem; line-height: 1.6;">
                    <div><strong>Nombre:</strong> {{ $cita->paciente?->nombre_completo }}</div>
                    <div><strong>Cédula (CI):</strong> {{ $cita->paciente?->ci }}</div>
                    <div><strong>Edad:</strong> {{ $cita->paciente?->fecha_nacimiento ? $cita->paciente->fecha_nacimiento->age . ' años' : 'N/D' }}</div>
                    <div><strong>Contacto:</strong> {{ $cita->paciente?->telefono }}</div>
                    
                    @if($cita->paciente?->responsableActivo)
                        <div style="margin-top: 0.5rem; background: var(--color-surface-hover); padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem;">
                            <strong>Tutor Autorizado:</strong> {{ $cita->paciente->responsableActivo->nombres }} ({{ $cita->paciente->responsableActivo->parentesco }})<br>
                            <span style="color: var(--color-text-muted);">Vigencia de Autorización: {{ $cita->paciente->responsableActivo->fecha_limite_vigencia->format('d/m/Y') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Datos de la Consulta y Médico -->
            <div>
                <h4 style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); margin: 0 0 0.75rem 0; letter-spacing: 0.5px;">
                    2. DETALLES DE LA CONSULTA
                </h4>
                <div style="font-size: 0.95rem; line-height: 1.6;">
                    <div><strong>Especialidad:</strong> <span style="color: var(--color-primary); font-weight: 700;">{{ $cita->medico?->especialidad?->nombre }}</span></div>
                    <div><strong>Médico:</strong> {{ $cita->medico?->user?->name }}</div>
                    <div><strong>Matrícula Médica:</strong> {{ $cita->medico?->nro_registro_medico }}</div>
                    <div><strong>Consultorio:</strong> Consultorio {{ $cita->medico?->especialidad_id * 10 + 2 }}</div>
                </div>
            </div>
        </div>

        <!-- Banner Destacado del Turno Asignado -->
        <div style="background: linear-gradient(135deg, #0d7c7c, #065e5e); color: white; border-radius: var(--radius-md); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85;">DÍA DE ATENCIÓN PROGRAMADO</div>
                <div style="font-size: 1.4rem; font-weight: 800;">
                    {{ $cita->fecha ? $cita->fecha->isoFormat('dddd, D [de] MMMM [de] YYYY') : '' }}
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85;">TURNO ASIGNADO</div>
                <div style="font-size: 1.8rem; font-weight: 800; font-family: monospace;">
                    {{ substr($cita->hora, 0, 5) }} hrs
                </div>
                <div style="font-size: 0.75rem; opacity: 0.85;">Duración estimada: 15 minutos</div>
            </div>
        </div>

        <!-- Motivo y Observaciones Clínicas -->
        @if($cita->motivo_consulta)
            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); margin: 0 0 0.4rem 0;">
                    Motivo de Consulta Indicado:
                </h4>
                <p style="margin: 0; font-size: 0.9rem; color: var(--color-text-main); font-style: italic;">
                    "{{ $cita->motivo_consulta }}"
                </p>
            </div>
        @endif

        @if($cita->observaciones_atencion)
            <div style="margin-bottom: 1.5rem; background: var(--color-surface-hover); padding: 1rem; border-radius: var(--radius-md); border-left: 3px solid var(--color-success);">
                <h4 style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--color-success); margin: 0 0 0.4rem 0;">
                    Diagnóstico y Observaciones de la Atención:
                </h4>
                <p style="margin: 0; font-size: 0.9rem; color: var(--color-text-main);">
                    {{ $cita->observaciones_atencion }}
                </p>
            </div>
        @endif

        <!-- Instrucciones y Pie de Seguridad -->
        <div style="border-top: 1px dashed var(--color-border); padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.75rem; color: var(--color-text-muted); max-width: 500px; line-height: 1.5;">
                <strong>Instrucciones para el paciente:</strong> Presentarse en ventanilla de recepción 15 minutos antes de la hora señalada para registrar su Check-in. Portar Cédula de Identidad original vigente. Si el paciente es menor de edad, debe acudir acompañado de su tutor acreditado.
            </div>

            <!-- Código QR / Token Simulado -->
            <div style="text-align: center; border: 1px solid var(--color-border); padding: 0.5rem; border-radius: var(--radius-sm); background: white;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect width="5" height="5" x="3" y="3" rx="1"/>
                    <rect width="5" height="5" x="16" y="3" rx="1"/>
                    <rect width="5" height="5" x="3" y="16" rx="1"/>
                    <path d="M21 16h-3a2 2 0 0 0-2 2v3"/>
                    <path d="M21 21v.01"/>
                    <path d="M12 7v3a2 2 0 0 1-2 2H7"/>
                    <path d="M3 12h.01"/>
                    <path d="M12 3h.01"/>
                    <path d="M12 16v.01"/>
                    <path d="M16 12h1"/>
                    <path d="M21 12v.01"/>
                    <path d="M12 21v-1"/>
                </svg>
                <div style="font-size: 0.65rem; font-family: monospace; color: var(--color-text-muted);">VALIDADO</div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body {
        background: white !important;
        color: black !important;
    }
    .no-print, .sidebar, .header, nav, #sidebar {
        display: none !important;
    }
    .content-area, .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .printable-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>
@endsection
