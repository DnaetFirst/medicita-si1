@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Bandeja de Órdenes de Laboratorio (CU15)</h1>
            <p>Recepción, validación y publicación de análisis clínicos.</p>
        </div>
    </div>

    <!-- Filtros de búsqueda -->
    <form class="card cycle-card cycle-grid" method="GET" action="{{ route('laboratorio.ordenes.index') }}">
        <label>Buscar por código o paciente
            <input type="text" class="form-input" name="search" value="{{ request('search') }}" placeholder="Código de orden, CI, nombre...">
        </label>
        <label>Estado
            <select name="estado" class="form-input">
                <option value="">Todos los estados</option>
                <option value="emitida" @selected(request('estado') === 'emitida')>Emitida</option>
                <option value="en_proceso" @selected(request('estado') === 'en_proceso')>En proceso</option>
                <option value="finalizada" @selected(request('estado') === 'finalizada')>Finalizada</option>
                <option value="cancelada" @selected(request('estado') === 'cancelada')>Cancelada</option>
            </select>
        </label>
        <div class="cycle-actions" style="align-self: flex-end;">
            <button class="btn btn-primary">Filtrar</button>
            <a class="btn btn-secondary" href="{{ route('laboratorio.ordenes.index') }}">Limpiar</a>
        </div>
    </form>

    <!-- Listado de órdenes -->
    <p>{{ $ordenes->total() }} órdenes registradas · Actualizado a las {{ now()->format('H:i') }}</p>

    @forelse($ordenes as $orden)
        <article class="card cycle-card">
            <div class="cycle-heading">
                <div>
                    <h2>{{ $orden->codigo_orden }} · {{ $orden->paciente->nombre_completo }}</h2>
                    <p>Solicitado por: Dr. {{ $orden->medico->user->name }} · {{ $orden->created_at->format('d/m/Y H:i') }}</p>
                    <small>Exámenes solicitados: {{ $orden->examenes->pluck('nombre')->join(', ') }}</small>
                </div>
                <strong class="badge {{ match($orden->estado) { 'finalizada' => 'badge-success', 'en_proceso' => 'badge-info', 'cancelada' => 'badge-danger', default => 'badge-secondary' } }}">
                    {{ ucfirst(str_replace('_', ' ', $orden->estado)) }}
                </strong>
            </div>

            <div class="cycle-actions">
                <a class="btn btn-secondary" href="{{ route('laboratorio.ordenes.show', $orden) }}">Ver detalle</a>
                @if($orden->estado !== 'finalizada' && $orden->estado !== 'cancelada')
                    <a class="btn btn-primary" href="{{ route('laboratorio.resultados.create', $orden) }}">Capturar resultados</a>
                @endif
            </div>
        </article>
    @empty
        <div class="card cycle-card">
            <h2>No hay órdenes de laboratorio</h2>
            <p>No se encontraron solicitudes con los criterios de búsqueda seleccionados.</p>
        </div>
    @endforelse

    {{ $ordenes->links() }}
</div>
@endsection
