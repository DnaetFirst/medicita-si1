@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Inscripción a Lista de Espera (CU18)</h1>
            <p>Registra tus preferencias clínicas para optar automáticamente por turnos liberados.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('espera.index') }}">Volver</a>
    </div>

    <form class="card cycle-card" method="POST" action="{{ route('espera.store') }}">
        @csrf
        <input type="hidden" name="operacion_id" value="{{ old('operacion_id', (string) Str::uuid()) }}">

        <!-- Paciente -->
        <label>Paciente para quien se solicita turno: *
            <select name="paciente_id" class="form-input" required>
                @foreach($pacientes as $pac)
                    <option value="{{ $pac->id }}" @selected(old('paciente_id', $pacientePreseleccionado?->id) == $pac->id)>
                        {{ $pac->nombre_completo }} (CI: {{ $pac->ci }})
                    </option>
                @endforeach
            </select>
        </label>

        <div class="cycle-grid">
            <!-- Especialidad -->
            <label>Especialidad Requerida: *
                <select name="especialidad_id" class="form-input" required>
                    <option value="">Seleccionar especialidad</option>
                    @foreach($especialidades as $esp)
                        <option value="{{ $esp->id }}" @selected(old('especialidad_id') == $esp->id)>{{ $esp->nombre }}</option>
                    @endforeach
                </select>
            </label>

            <!-- Médico Opcional -->
            <label>Médico Preferente (Opcional):
                <select name="medico_id" class="form-input">
                    <option value="">Cualquier médico de la especialidad</option>
                    @foreach($medicos as $med)
                        <option value="{{ $med->id }}" @selected(old('medico_id') == $med->id)>Dr. {{ $med->user->name }} ({{ $med->especialidad->nombre }})</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="cycle-grid">
            <!-- Rango de Fechas -->
            <label>Fecha Desde: *
                <input type="date" class="form-input" name="fecha_desde" value="{{ old('fecha_desde', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required>
            </label>

            <label>Fecha Hasta: *
                <input type="date" class="form-input" name="fecha_hasta" value="{{ old('fecha_hasta', today()->addDays(14)->toDateString()) }}" min="{{ today()->toDateString() }}" required>
            </label>

            <!-- Franja Horaria -->
            <label>Preferencia Horaria: *
                <select name="franja_horaria" class="form-input" required>
                    <option value="cualquiera" @selected(old('franja_horaria') === 'cualquiera')>Cualquier horario</option>
                    <option value="manana" @selected(old('franja_horaria') === 'manana')>Solo Mañana (08:00 - 13:00)</option>
                    <option value="tarde" @selected(old('franja_horaria') === 'tarde')>Solo Tarde (14:00 - 20:00)</option>
                </select>
            </label>

            <!-- Anticipación Mínima -->
            <label>Anticipación Mínima de Aviso (Horas): *
                <input type="number" class="form-input" name="anticipacion_minima_horas" value="{{ old('anticipacion_minima_horas', 2) }}" min="1" max="72" required>
                <small style="color: var(--color-text-muted);">Para no ofrecerte cupos con menos horas de las que necesitas para llegar.</small>
            </label>
        </div>

        <!-- Cita previa a adelantar (opcional) -->
        @if($citasPaciente->isNotEmpty())
            <div style="margin-top: 1rem;">
                <label>¿Deseas adelantar una cita que ya tienes agendada? (Opcional):
                    <select name="cita_id" class="form-input">
                        <option value="">No, es una reserva nueva independiente</option>
                        @foreach($citasPaciente as $cita)
                            <option value="{{ $cita->id }}" @selected(old('cita_id') == $cita->id)>
                                Ficha {{ $cita->codigo_ficha }} · {{ $cita->medico->especialidad->nombre }} con Dr. {{ $cita->medico->user->name }} el {{ $cita->fecha->format('d/m/Y') }} a las {{ $cita->hora }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <small style="color: var(--color-text-muted);">Si seleccionas una cita previa, solo se te ofrecerán cupos que ocurran antes de tu cita actual, y al aceptar se reprogramará tu cita conservando su ficha.</small>
            </div>
        @endif

        <div class="cycle-actions" style="margin-top: 1.5rem;">
            <button class="btn btn-primary">Registrar en Lista de Espera</button>
            <a class="btn btn-secondary" href="{{ route('espera.index') }}">Cancelar</a>
        </div>
    </form>
</div>
@endsection
