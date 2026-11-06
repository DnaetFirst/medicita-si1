@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Oferta de Cupo Recuperado (CU18)</h1>
            <p>Se ha liberado un turno exclusivo para ti por estar inscrito en la lista de espera.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('espera.index') }}">Volver</a>
    </div>

    <!-- Tarjeta de la Oferta -->
    <div class="card cycle-card" style="border: 2px solid var(--color-primary); padding: 2rem;">
        <div class="cycle-heading">
            <div>
                <span class="badge badge-info" style="font-size: 0.9rem; margin-bottom: 0.5rem; display: inline-block;">Oferta Exclusiva Temporal</span>
                <h2 style="color: var(--color-primary); font-size: 1.5rem;">
                    {{ $oferta->slot->fecha->format('d/m/Y') }} a las {{ $oferta->slot->hora_inicio }}
                </h2>
                <p style="font-size: 1.1rem; margin-top: 0.25rem;">
                    Especialidad: <strong>{{ $oferta->slot->medico->especialidad->nombre }}</strong>
                </p>
                <p>Profesional: Dr. {{ $oferta->slot->medico->user->name }}</p>
                <p>Paciente: <strong>{{ $oferta->listaEspera->paciente->nombre_completo }}</strong></p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.85rem; color: var(--color-text-muted);">EXPIRACIÓN DE LA OFERTA</div>
                <div style="font-family: monospace; font-size: 1.3rem; font-weight: 700; color: var(--color-danger);">
                    {{ $oferta->fecha_vencimiento->format('H:i:s') }}
                </div>
                <small style="color: var(--color-text-muted);">Plazo del servidor: 10 minutos desde la liberación</small>
            </div>
        </div>

        @if($oferta->listaEspera->citaPrevia)
            <div style="margin-top: 1.5rem; padding: 1rem; background: var(--color-surface-hover); border-radius: var(--border-radius-sm);">
                <strong>Nota sobre tu cita actual:</strong>
                <p style="font-size: 0.9rem; margin-top: 0.25rem;">
                    Actualmente tienes la ficha <strong>{{ $oferta->listaEspera->citaPrevia->codigo_ficha }}</strong> programada para el {{ $oferta->listaEspera->citaPrevia->fecha->format('d/m/Y') }}. Al aceptar este nuevo horario, se reprogramará automáticamente conservando tu identificador de cita y se liberará tu horario previo.
                </p>
            </div>
        @endif

        <div class="cycle-actions" style="margin-top: 2rem; justify-content: flex-end;">
            <form action="{{ route('espera.ofertas.aceptar', $oferta) }}" method="POST">
                @csrf
                <input type="hidden" name="operacion_id" value="{{ Str::uuid() }}">
                <button class="btn btn-success" style="font-size: 1rem; padding: 0.75rem 1.5rem;">
                    Aceptar este Turno
                </button>
            </form>

            <form action="{{ route('espera.ofertas.rechazar', $oferta) }}" method="POST" onsubmit="return confirm('¿Estás seguro de declinar este horario? El cupo se ofrecerá al siguiente paciente.')">
                @csrf
                <button class="btn btn-secondary">
                    Declinar horario
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
