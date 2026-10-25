@extends('layouts.app')

@section('content')
<div style="max-width: 860px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
                Modificar Ficha de Paciente (CU7)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
                Actualización de datos para {{ $paciente->nombre_completo }} (CI: {{ $paciente->ci }})
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-secondary">
                Ver Ficha
            </a>
            <a href="{{ route('pacientes.index') }}" class="btn btn-secondary">
                Volver
            </a>
        </div>
    </div>

    @if($errors->any())
        <div style="background: #fee2e2; border-left: 4px solid var(--color-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <div style="font-weight: 700; color: #991b1b; margin-bottom: 0.25rem;">Corrige los siguientes errores en el formulario:</div>
            <ul style="margin: 0; padding-left: 1.25rem; color: #991b1b; font-size: 0.875rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('pacientes.update', $paciente) }}" class="card" style="padding: 2rem;">
        @csrf
        @method('PUT')

        <!-- Sección 1: Datos Personales -->
        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
            1. Datos Personales
        </h3>

        <div class="grid-2" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label">Cédula de Identidad (CI) *</label>
                <input type="text" name="ci" class="form-input" required value="{{ old('ci', $paciente->ci) }}">
            </div>
            <div>
                <label class="form-label">Fecha de Nacimiento *</label>
                <input type="date" name="fecha_nacimiento" class="form-input" required value="{{ old('fecha_nacimiento', $paciente->fecha_nacimiento?->format('Y-m-d')) }}" max="{{ date('Y-m-d') }}">
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label">Nombres *</label>
                <input type="text" name="nombres" class="form-input" required value="{{ old('nombres', $paciente->nombres) }}">
            </div>
            <div>
                <label class="form-label">Apellidos *</label>
                <input type="text" name="apellidos" class="form-input" required value="{{ old('apellidos', $paciente->apellidos) }}">
            </div>
        </div>

        <div class="grid-3" style="margin-bottom: 1.5rem;">
            <div>
                <label class="form-label">Género</label>
                <select name="genero" class="form-input">
                    <option value="">Seleccione género...</option>
                    <option value="Masculino" {{ old('genero', $paciente->genero) === 'Masculino' ? 'selected' : '' }}>Masculino</option>
                    <option value="Femenino" {{ old('genero', $paciente->genero) === 'Femenino' ? 'selected' : '' }}>Femenino</option>
                    <option value="Otro" {{ old('genero', $paciente->genero) === 'Otro' ? 'selected' : '' }}>Otro</option>
                </select>
            </div>
            <div>
                <label class="form-label">Grupo Sanguíneo</label>
                <select name="grupo_sanguineo" class="form-input">
                    <option value="">Seleccione grupo...</option>
                    @foreach(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $gs)
                        <option value="{{ $gs }}" {{ old('grupo_sanguineo', $paciente->grupo_sanguineo) === $gs ? 'selected' : '' }}>{{ $gs }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Estado de la Ficha *</label>
                <select name="activo" class="form-input">
                    <option value="1" {{ old('activo', $paciente->activo) ? 'selected' : '' }}>Activo</option>
                    <option value="0" {{ !old('activo', $paciente->activo) ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>
        </div>

        <!-- Sección 2: Contacto y Residencia -->
        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
            2. Contacto y Residencia
        </h3>

        <div class="grid-2" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label">Teléfono / Celular *</label>
                <input type="text" name="telefono" class="form-input" required value="{{ old('telefono', $paciente->telefono) }}">
            </div>
            <div>
                <label class="form-label">Correo Electrónico</label>
                <input type="email" name="email" class="form-input" value="{{ old('email', $paciente->email) }}">
            </div>
        </div>

        <div style="margin-bottom: 1rem;">
            <label class="form-label">Dirección Domiciliaria</label>
            <input type="text" name="direccion" class="form-input" value="{{ old('direccion', $paciente->direccion) }}">
        </div>

        <div class="grid-2" style="margin-bottom: 1.5rem;">
            <div>
                <label class="form-label">Contacto de Emergencia (Nombre)</label>
                <input type="text" name="contacto_emergencia_nombre" class="form-input" value="{{ old('contacto_emergencia_nombre', $paciente->contacto_emergencia_nombre) }}">
            </div>
            <div>
                <label class="form-label">Contacto de Emergencia (Teléfono)</label>
                <input type="text" name="contacto_emergencia_telefono" class="form-input" value="{{ old('contacto_emergencia_telefono', $paciente->contacto_emergencia_telefono) }}">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="padding: 0.6rem 2rem;">
                Actualizar Datos del Paciente
            </button>
        </div>
    </form>
</div>
@endsection
