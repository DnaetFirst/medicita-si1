@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Mis Resultados de Laboratorio (CU15)</h1>
            <p>Consulta tus informes de análisis clínicos validados y publicados.</p>
        </div>
    </div>

    <!-- Selección de Representado / Paciente -->
    @if($pacientes->count() > 1)
        <form class="card cycle-card" method="GET" action="{{ route('laboratorio.mis_resultados') }}">
            <label>Seleccionar Paciente:
                <select name="paciente_id" class="form-input" onchange="this.form.submit()">
                    @foreach($pacientes as $p)
                        <option value="{{ $p->id }}" @selected($paciente?->id === $p->id)>{{ $p->nombre_completo }} (CI: {{ $p->ci }})</option>
                    @endforeach
                </select>
            </label>
        </form>
    @endif

    @if($paciente)
        <p>{{ $resultados->total() }} informes disponibles para {{ $paciente->nombre_completo }}</p>

        @forelse($resultados as $res)
            <article class="card cycle-card">
                <div class="cycle-heading">
                    <div>
                        <h2>Informe: {{ $res->codigo_resultado }}</h2>
                        <p>Orden: {{ $res->orden->codigo_orden }} · Publicado el {{ $res->fecha_publicacion->format('d/m/Y H:i') }}</p>
                        <small>Médico solicitante: Dr. {{ $res->orden->medico->user->name }} ({{ $res->orden->medico->especialidad->nombre }})</small>
                    </div>
                    <strong class="badge badge-success">Publicado</strong>
                </div>

                <div class="cycle-actions">
                    <a class="btn btn-secondary" href="{{ route('laboratorio.resultados.show', $res) }}">Ver detalles del análisis</a>
                    @if($res->archivo_pdf_path)
                        <a class="btn btn-primary" href="{{ route('laboratorio.resultados.descargar', $res) }}">Descargar PDF Oficial</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="card cycle-card">
                <h2>No hay resultados disponibles</h2>
                <p>No se encontraron resultados de laboratorio publicados para este paciente.</p>
            </div>
        @endforelse

        {{ $resultados->links() }}
    @else
        <div class="card cycle-card">
            <h2>No tienes pacientes autorizados</h2>
            <p>No cuentas con un paciente asociado o tu autorización como tutor no tiene permiso para ver resultados de laboratorio.</p>
        </div>
    @endif
</div>
@endsection
