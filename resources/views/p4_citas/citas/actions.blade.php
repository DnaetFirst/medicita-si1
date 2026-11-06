@php
    $actor = auth()->user();
    $inicio = \Carbon\Carbon::parse($cita->fecha->toDateString().' '.$cita->hora);
    $acciones = [];
    if ($actor->isPaciente() && $cita->estado === 'programada' && $inicio->isFuture()) {
        $acciones['confirmar'] = 'Confirmar asistencia';
    }
    if ($actor->isRecepcionista() && in_array($cita->estado, ['programada', 'confirmada'])) {
        if ($cita->fecha->isToday()) { $acciones['checkin'] = 'Registrar llegada'; }
        if ($inicio->copy()->addMinutes(15)->lte(now())) { $acciones['inasistencia'] = 'Registrar inasistencia'; }
    }
    if ($actor->isMedico() && $actor->medico?->id === $cita->medico_id) {
        if ($cita->estado === 'en_espera' && $cita->fecha->isToday()) { $acciones['iniciar'] = 'Iniciar atención'; }
        if ($cita->estado === 'en_atencion') { $acciones['finalizar'] = 'Finalizar atención'; }
    }
    $puedeCambiar = !$actor->isMedico() && in_array($cita->estado, ['programada', 'confirmada', 'postergada']) && (!$actor->isPaciente() || $cita->estado === 'postergada' || $inicio->isFuture());
@endphp
<div class="cycle-actions no-print">
    @foreach($acciones as $ruta => $etiqueta)<form action="{{ route('citas.'.$ruta, $cita) }}" method="POST">@csrf<input type="hidden" name="version" value="{{ $cita->version }}"><input type="hidden" name="operacion_id" value="{{ Str::uuid() }}"><button class="btn btn-primary">{{ $etiqueta }}</button></form>@endforeach
    @if($actor->isMedico())<a class="btn btn-secondary" href="{{ route('historia.show', $cita->paciente) }}">Historia clínica</a>@endif
    @if($puedeCambiar)<a class="btn btn-secondary" href="{{ route('citas.reprogramar', $cita) }}">Reprogramar</a>@endif
    <a class="btn btn-secondary" href="{{ route('historial.index', ['paciente_id' => $cita->paciente_id]) }}">Historial</a>
</div>
@if($puedeCambiar)
<details class="card cycle-card no-print"><summary>Cancelar cita</summary><form action="{{ route('citas.cancelar', $cita) }}" method="POST">@csrf<input type="hidden" name="version" value="{{ $cita->version }}"><input type="hidden" name="operacion_id" value="{{ old('operacion_id', (string) Str::uuid()) }}"><label>Motivo de cancelación<textarea class="form-input" name="motivo_cancelacion" required minlength="5" maxlength="500">{{ old('motivo_cancelacion') }}</textarea></label><button class="btn btn-danger">Confirmar cancelación</button></form></details>
@endif
