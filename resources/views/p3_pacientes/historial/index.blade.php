@extends('layouts.app')
@section('content')
<div class="cycle-page">
    <div class="cycle-heading"><div><h1>Historial de citas</h1><p>Consulta tus turnos y los cambios registrados.</p></div>@unless(auth()->user()->isMedico())<a class="btn btn-primary" href="{{ route('citas.nueva', ['paciente_id' => $paciente?->id]) }}">Solicitar ficha</a>@endunless</div>
    <form class="card cycle-card cycle-grid" method="GET" action="{{ route('historial.index') }}">
        <label>Paciente<select name="paciente_id" class="form-input"><option value="">Seleccionar</option>@foreach($pacientes as $opcion)<option value="{{ $opcion->id }}" @selected($paciente?->id === $opcion->id)>{{ $opcion->nombre_completo }}</option>@endforeach</select></label>
        <label>Desde<input type="date" class="form-input" name="desde" value="{{ request('desde') }}"></label>
        <label>Hasta<input type="date" class="form-input" name="hasta" value="{{ request('hasta') }}"></label>
        <label>Estado<select name="estado" class="form-input"><option value="">Todos</option>@foreach(['programada', 'confirmada', 'en_espera', 'en_atencion', 'atendida', 'postergada', 'cancelada', 'inasistencia'] as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ ucfirst(str_replace('_', ' ', $estado)) }}</option>@endforeach</select></label>
        <label>Especialidad<select class="form-input" name="especialidad_id"><option value="">Todas</option>@foreach($especialidades as $especialidad)<option value="{{ $especialidad->id }}" @selected(request('especialidad_id') == $especialidad->id)>{{ $especialidad->nombre }}</option>@endforeach</select></label>
        <label>Médico<select class="form-input" name="medico_id"><option value="">Todos</option>@foreach($medicos as $medico)<option value="{{ $medico->id }}" @selected(request('medico_id') == $medico->id)>{{ $medico->user?->name }}</option>@endforeach</select></label>
        <div class="cycle-actions"><button class="btn btn-primary">Filtrar</button><a class="btn btn-secondary" href="{{ route('historial.index') }}">Limpiar</a></div>
    </form>
    @if($paciente && auth()->user()->isMedico())<p><a class="btn btn-primary" href="{{ route('historia.show', $paciente) }}">Abrir historia clínica de {{ $paciente->nombre_completo }}</a></p>@endif
    @unless(auth()->user()->isMedico())@foreach($derivaciones as $derivacion)<div class="card cycle-card"><strong>Derivación #{{ $derivacion->id }} · {{ $derivacion->especialidad->nombre }}</strong><p><a class="btn btn-secondary" href="{{ route('citas.nueva', ['derivacion_id' => $derivacion->id]) }}">Solicitar cita para esta derivación</a></p></div>@endforeach
    @endunless
    <p>{{ $citas->total() }} citas · Consulta actualizada a las {{ now()->format('H:i') }}</p>
    @forelse($citas as $cita)
        <article class="card cycle-card">
            <div class="cycle-heading"><div><h2>{{ $cita->fecha->format('d/m/Y') }} · {{ substr($cita->hora, 0, 5) }}</h2><p>{{ $cita->medico->user->name }} · {{ $cita->medico->especialidad->nombre }}</p><small>Ficha {{ $cita->codigo_ficha }}</small></div><strong class="badge">{{ ucfirst(str_replace('_', ' ', $cita->estado)) }}</strong></div>
            <div class="cycle-actions">@if(!auth()->user()->isMedico() || auth()->user()->medico?->id === $cita->medico_id)<a class="btn btn-secondary" href="{{ route('citas.show', $cita) }}">Ver ficha y acciones</a>@endif</div>
            <details><summary>Ver cambios ({{ $cita->historial->count() }})</summary><ol class="cycle-timeline">@forelse($cita->historial->sortByDesc('id') as $evento)<li><strong>{{ ucfirst(strtolower($evento->operacion)) }}</strong> · {{ $evento->created_at->format('d/m/Y H:i') }}<p>{{ str_replace('_', ' ', $evento->estado_anterior ?? 'Nueva cita') }} → {{ str_replace('_', ' ', $evento->estado_nuevo) }}</p>@if($evento->fecha_anterior && !$evento->fecha_anterior->equalTo($evento->fecha_nueva))<p>{{ $evento->fecha_anterior->format('d/m/Y H:i') }} → {{ $evento->fecha_nueva->format('d/m/Y H:i') }}</p>@endif<small>Registrado por {{ $evento->autor?->name ?? 'Sistema' }}</small></li>@empty<li>Registro anterior a la incorporación del historial. No se reconstruyen cambios no documentados.</li>@endforelse</ol></details>
        </article>
    @empty<div class="card cycle-card"><h2>No hay citas para estos filtros</h2><p>Selecciona otro paciente o ajusta las fechas.</p></div>@endforelse
    {{ $citas->links() }}
</div>
@endsection
