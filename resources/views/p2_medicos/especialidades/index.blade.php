@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Especialidades Médicas (CU3)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Catálogo clínico de ramas asistenciales y configuración visual para turnos
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openCreateModal()" style="box-shadow: 0 4px 10px rgba(13, 124, 124, 0.2);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva Especialidad
    </button>
</div>

<!-- Grid de Especialidades -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.25rem;">
    @foreach($especialidades as $esp)
        <div class="card" style="border-top: 4px solid {{ $esp->color }}; position: relative; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.625rem;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: {{ $esp->color }}15; color: {{ $esp->color }}; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem;">
                            {{ substr($esp->codigo, 0, 3) }}
                        </div>
                        <div>
                            <h3 style="font-size: var(--font-md); font-weight: 700; color: var(--color-primary); line-height: 1.2;">
                                {{ $esp->nombre }}
                            </h3>
                            <span style="font-size: 0.75rem; color: var(--color-text-muted); font-family: monospace;">
                                {{ $esp->codigo }}
                            </span>
                        </div>
                    </div>

                    @if($esp->activo)
                        <span class="badge badge-success">Activa</span>
                    @else
                        <span class="badge" style="background: #E2E8F0; color: #718096;">Inactiva</span>
                    @endif
                </div>

                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 1rem; line-height: 1.4;">
                    {{ $esp->descripcion ?: 'Sin descripción detallada registrada.' }}
                </p>
            </div>

            <div style="border-top: 1px solid var(--color-border); padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.75rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 0.25rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <strong>{{ $esp->medicos_count }}</strong> médico(s) asignado(s)
                </div>

                <div style="display: flex; gap: 0.375rem;">
                    <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick='openEditModal(@json($esp))'>
                        Editar
                    </button>

                    <form action="{{ route('especialidades.toggle', $esp) }}" method="POST" style="display: inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                            {{ $esp->activo ? 'Pausar' : 'Activar' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Modal Crear / Editar -->
<div id="espModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 500px; margin-bottom: 0; box-shadow: var(--shadow-xl);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem;">
            <h2 id="modalTitle" style="font-size: var(--font-lg); font-weight: 700; color: var(--color-primary);">
                Nueva Especialidad
            </h2>
            <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--color-text-muted);">
                &times;
            </button>
        </div>

        <form id="espForm" method="POST" action="{{ route('especialidades.store') }}">
            @csrf
            <div id="methodField"></div>

            <div class="form-group">
                <label class="form-label" for="espNombre">Nombre de la Especialidad *</label>
                <input type="text" name="nombre" id="espNombre" class="form-input" placeholder="Ej: Traumatología" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label class="form-label" for="espCodigo">Código Único *</label>
                    <input type="text" name="codigo" id="espCodigo" class="form-input" placeholder="Ej: TRAUM-01" required>
                </div>
                <div>
                    <label class="form-label" for="espColor">Color Identificativo *</label>
                    <input type="color" name="color" id="espColor" class="form-input" value="#0D7C7C" style="height: 38px; padding: 2px 4px; cursor: pointer;" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="espDesc">Descripción Clínica</label>
                <textarea name="descripcion" id="espDesc" class="form-input" rows="3" placeholder="Alcance y tipos de consulta que atiende..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--color-border); padding-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Especialidad</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').innerText = 'Nueva Especialidad';
    document.getElementById('espForm').action = "{{ route('especialidades.store') }}";
    document.getElementById('methodField').innerHTML = '';
    document.getElementById('espNombre').value = '';
    document.getElementById('espCodigo').value = '';
    document.getElementById('espColor').value = '#0D7C7C';
    document.getElementById('espDesc').value = '';
    document.getElementById('espModal').style.display = 'flex';
}

function openEditModal(esp) {
    document.getElementById('modalTitle').innerText = 'Editar: ' + esp.nombre;
    document.getElementById('espForm').action = "/especialidades/" + esp.id;
    document.getElementById('methodField').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('espNombre').value = esp.nombre;
    document.getElementById('espCodigo').value = esp.codigo;
    document.getElementById('espColor').value = esp.color;
    document.getElementById('espDesc').value = esp.descripcion || '';
    document.getElementById('espModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('espModal').style.display = 'none';
}
</script>
@endsection
