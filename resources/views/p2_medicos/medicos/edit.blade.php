@extends('layouts.app')

@section('content')
<div style="max-width: 760px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
                Modificar Médico: {{ $medico->user?->name }} (CU4)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
                Actualiza datos de contacto, matrícula, especialidad o contraseña
            </p>
        </div>
        <a href="{{ route('medicos.index') }}" class="btn btn-secondary">
            ← Volver al Listado
        </a>
    </div>

    @if($errors->any())
        <div class="card" style="background: var(--color-danger-bg); border-color: var(--color-danger); color: var(--color-danger); padding: 0.75rem 1rem;">
            @foreach($errors->all() as $err)
                <div>• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <form action="{{ route('medicos.update', $medico) }}" method="POST">
            @csrf
            @method('PUT')

            <h3 style="font-size: var(--font-md); font-weight: 600; color: var(--color-primary); margin-bottom: 0.75rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
                1. Información Profesional y Especialidad
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="especialidad_id">Especialidad Asignada *</label>
                    <select name="especialidad_id" id="especialidad_id" class="form-input" required>
                        @foreach($especialidades as $esp)
                            <option value="{{ $esp->id }}" {{ old('especialidad_id', $medico->especialidad_id) == $esp->id ? 'selected' : '' }}>
                                {{ $esp->nombre }} ({{ $esp->codigo }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="nro_registro_medico">Nro. Registro / Matrícula Profesional *</label>
                    <input type="text" name="nro_registro_medico" id="nro_registro_medico" class="form-input" value="{{ old('nro_registro_medico', $medico->nro_registro_medico) }}" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="biografia">Perfil Profesional / Biografía</label>
                <textarea name="biografia" id="biografia" class="form-input" rows="2">{{ old('biografia', $medico->biografia) }}</textarea>
            </div>

            <h3 style="font-size: var(--font-md); font-weight: 600; color: var(--color-primary); margin-top: 1.5rem; margin-bottom: 0.75rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
                2. Datos de Cuenta y Contacto
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="name">Nombre Completo *</label>
                    <input type="text" name="name" id="name" class="form-input" value="{{ old('name', $medico->user?->name) }}" required>
                </div>
                <div>
                    <label class="form-label" for="ci">Cédula de Identidad (CI) *</label>
                    <input type="text" name="ci" id="ci" class="form-input" value="{{ old('ci', $medico->user?->ci) }}" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="email">Correo Electrónico *</label>
                    <input type="email" name="email" id="email" class="form-input" value="{{ old('email', $medico->user?->email) }}" required>
                </div>
                <div>
                    <label class="form-label" for="telefono">Teléfono / Celular</label>
                    <input type="text" name="telefono" id="telefono" class="form-input" value="{{ old('telefono', $medico->telefono_contacto) }}">
                </div>
            </div>

            <div class="form-group" style="max-width: 250px;">
                <label class="form-label" for="estado">Estado del Médico *</label>
                <select name="estado" id="estado" class="form-input" required>
                    <option value="activo" {{ old('estado', $medico->estado) === 'activo' ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ old('estado', $medico->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                    <option value="de_baja" {{ old('estado', $medico->estado) === 'de_baja' ? 'selected' : '' }}>De Baja</option>
                </select>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--color-border); margin-bottom: 1.5rem;">
                <h4 style="font-size: var(--font-sm); font-weight: 600; color: var(--color-primary); margin-bottom: 0.5rem;">
                    Restablecer Contraseña (Opcional)
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <input type="password" name="password" class="form-input" placeholder="Nueva contraseña (dejar vacío para mantener)">
                    </div>
                    <div>
                        <input type="password" name="password_confirmation" class="form-input" placeholder="Repite la contraseña">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
                <a href="{{ route('medicos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">
                    Actualizar Médico
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
