@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Lista de Espera y Recuperación de Cupos (CU18)</h1>
            <p>Monitoreo de turnos liberados y asignación preferente para pacientes.</p>
        </div>
        @if(auth()->user()->isPaciente() || auth()->user()->isRecepcionista())
            <a class="btn btn-primary" href="{{ route('espera.create') }}">Inscribirse en Lista de Espera</a>
        @endif
    </div>

    <!-- Ofertas Activas Pendientes (Urgente: 10 minutos para responder) -->
    @if($ofertasPendientes->isNotEmpty())
        <div class="card cycle-card" style="border: 2px solid var(--color-primary); background: rgba(13, 124, 124, 0.05);">
            <h2 style="color: var(--color-primary);">¡Tienes un cupo liberado disponible!</h2>
            <p>Se ha liberado un turno compatible con tus preferencias. Tienes 10 minutos para confirmarlo antes de que pase al siguiente paciente:</p>

            @foreach($ofertasPendientes as $oferta)
                <div class="card" style="margin-top: 1rem; padding: 1rem;">
                    <div class="cycle-heading">
                        <div>
                            <h3>{{ $oferta->slot->medico->especialidad->nombre }} · Dr. {{ $oferta->slot->medico->user->name }}</h3>
                            <p>Fecha del turno: <strong>{{ $oferta->slot->fecha->format('d/m/Y') }}</strong> a las <strong>{{ $oferta->slot->hora_inicio }}</strong></p>
                            <small>Expira: {{ $oferta->fecha_vencimiento->format('H:i:s') }} (Hora del servidor)</small>
                        </div>
                        <div class="cycle-actions">
                            <a class="btn btn-primary" href="{{ route('espera.ofertas.show', $oferta) }}">Revisar y Aceptar Cupo</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Mis Inscripciones Activas -->
    <h2>Inscripciones en Lista de Espera</h2>
    @forelse($inscripciones as $esp)
        <article class="card cycle-card">
            <div class="cycle-heading">
                <div>
                    <h3>{{ $esp->especialidad->nombre }} · Paciente: {{ $esp->paciente->nombre_completo }}</h3>
                    <p>Médico preferente: {{ $esp->medico?->user->name ?? 'Cualquier profesional disponible' }}</p>
                    <small>Rango: del {{ $esp->fecha_desde->format('d/m/Y') }} al {{ $esp->fecha_hasta->format('d/m/Y') }} · Franja: {{ ucfirst($esp->franja_horaria) }}</small>
                    @if($esp->citaPrevia)
                        <div style="margin-top: 0.25rem; font-size: 0.85rem; color: var(--color-primary);">
                            Solicitud para adelantar cita previa: Ficha {{ $esp->citaPrevia->codigo_ficha }} ({{ $esp->citaPrevia->fecha->format('d/m/Y') }})
                        </div>
                    @endif
                </div>
                <strong class="badge {{ match($esp->estado) { 'atendida' => 'badge-success', 'activa' => 'badge-info', default => 'badge-secondary' } }}">
                    {{ ucfirst($esp->estado) }}
                </strong>
            </div>

            <div class="cycle-actions">
                @if($esp->isActiva())
                    <form action="{{ route('espera.destroy', $esp) }}" method="POST" onsubmit="return confirm('¿Deseas retirar tu inscripción de la lista de espera?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger">Retirarse de la lista</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="card cycle-card">
            <h2>No hay inscripciones activas</h2>
            <p>Si no encuentras turno disponible en la fecha deseada, inscríbete en la lista de espera para recibir una oferta automática cuando alguien cancele o reprograme su cita.</p>
        </div>
    @endforelse

    {{ $inscripciones->links() }}
</div>
@endsection
