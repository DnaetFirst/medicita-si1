@extends('layouts.app')

@section('content')
<div style="max-width: 860px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
                Registrar Nuevo Paciente (CU7)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
                Ingresa la información personal, clínica y responsable autorizado del paciente
            </p>
        </div>
        <a href="{{ route('pacientes.index') }}" class="btn btn-secondary">
            &larr; Volver al Catálogo
        </a>
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

    <form method="POST" action="{{ route('pacientes.store') }}" class="card" style="padding: 2rem;">
        @csrf

        <!-- Sección 1: Datos Personales -->
        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            1. Datos Personales y de Identificación
        </h3>

        <div class="grid-2" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label">Cédula de Identidad (CI) *</label>
                <input type="text" name="ci" class="form-input" required value="{{ old('ci') }}" placeholder="Ej. 8472910">
            </div>
            <div>
                <label class="form-label">Fecha de Nacimiento *</label>
                <input type="date" name="fecha_nacimiento" class="form-input" required value="{{ old('fecha_nacimiento') }}" max="{{ date('Y-m-d') }}">
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label">Nombres Completos *</label>
                <input type="text" name="nombres" class="form-input" required value="{{ old('nombres') }}" placeholder="Ej. Carlos Eduardo">
            </div>
            <div>
                <label class="form-label">Apellidos *</label>
                <input type="text" name="apellidos" class="form-input" required value="{{ old('apellidos') }}" placeholder="Ej. Gómez Salazar">
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 1.5rem;">
            <div>
                <label class="form-label">Género</label>
                <select name="genero" class="form-input">
                    <option value="">Seleccione género...</option>
                    <option value="Masculino" {{ old('genero') === 'Masculino' ? 'selected' : '' }}>Masculino</option>
                    <option value="Femenino" {{ old('genero') === 'Femenino' ? 'selected' : '' }}>Femenino</option>
                    <option value="Otro" {{ old('genero') === 'Otro' ? 'selected' : '' }}>Otro</option>
                </select>
            </div>
            <div>
                <label class="form-label">Grupo Sanguíneo</label>
                <select name="grupo_sanguineo" class="form-input">
                    <option value="">Seleccione grupo...</option>
                    <option value="O+" {{ old('grupo_sanguineo') === 'O+' ? 'selected' : '' }}>O+</option>
                    <option value="O-" {{ old('grupo_sanguineo') === 'O-' ? 'selected' : '' }}>O-</option>
                    <option value="A+" {{ old('grupo_sanguineo') === 'A+' ? 'selected' : '' }}>A+</option>
                    <option value="A-" {{ old('grupo_sanguineo') === 'A-' ? 'selected' : '' }}>A-</option>
                    <option value="B+" {{ old('grupo_sanguineo') === 'B+' ? 'selected' : '' }}>B+</option>
                    <option value="B-" {{ old('grupo_sanguineo') === 'B-' ? 'selected' : '' }}>B-</option>
                    <option value="AB+" {{ old('grupo_sanguineo') === 'AB+' ? 'selected' : '' }}>AB+</option>
                    <option value="AB-" {{ old('grupo_sanguineo') === 'AB-' ? 'selected' : '' }}>AB-</option>
                </select>
            </div>
        </div>

        <!-- Sección 2: Datos de Contacto y Emergencia -->
        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            2. Contacto y Residencia
        </h3>

        <div class="grid-2" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label">Teléfono / Celular *</label>
                <input type="text" name="telefono" class="form-input" required value="{{ old('telefono') }}" placeholder="Ej. 77012345">
            </div>
            <div>
                <label class="form-label">Correo Electrónico</label>
                <input type="email" name="email" class="form-input" value="{{ old('email') }}" placeholder="paciente@correo.com">
            </div>
        </div>

        <div style="margin-bottom: 1rem;">
            <label class="form-label">Dirección Domiciliaria</label>
            <input type="text" name="direccion" class="form-input" value="{{ old('direccion') }}" placeholder="Av. San Martín #450, Barrio Equipetrol">
        </div>

        <div class="grid-2" style="margin-bottom: 1.5rem;">
            <div>
                <label class="form-label">Contacto de Emergencia (Nombre)</label>
                <input type="text" name="contacto_emergencia_nombre" class="form-input" value="{{ old('contacto_emergencia_nombre') }}" placeholder="Ej. María Elena Salazar">
            </div>
            <div>
                <label class="form-label">Contacto de Emergencia (Teléfono)</label>
                <input type="text" name="contacto_emergencia_telefono" class="form-input" value="{{ old('contacto_emergencia_telefono') }}" placeholder="Ej. 77998877">
            </div>
        </div>

        <!-- Sección 3: Tutor o Responsable Legal Autorizado (CU7) -->
        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                3. Responsable Autorizado / Tutor (Menores de edad o tutelados)
            </div>
            <label style="font-size: 0.85rem; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                <input type="checkbox" name="tiene_responsable" id="toggleResponsable" value="1" {{ old('tiene_responsable') ? 'checked' : '' }} onchange="toggleResponsableFields()">
                ¿Tiene tutor legal?
            </label>
        </h3>

        <div id="responsableFields" style="display: {{ old('tiene_responsable') ? 'block' : 'none' }}; background: var(--color-surface-hover); padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px dashed var(--color-border);">
            <div class="grid-2" style="margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Cédula del Tutor / Responsable *</label>
                    <input type="text" name="resp_ci" class="form-input" value="{{ old('resp_ci') }}" placeholder="Ej. 3481902">
                </div>
                <div>
                    <label class="form-label">Nombre Completo del Tutor *</label>
                    <input type="text" name="resp_nombres" class="form-input" value="{{ old('resp_nombres') }}" placeholder="Ej. Roberto Gómez">
                </div>
            </div>

            <div class="grid-3" style="margin-bottom: 0.5rem;">
                <div>
                    <label class="form-label">Parentesco *</label>
                    <select name="resp_parentesco" class="form-input">
                        <option value="Padre" {{ old('resp_parentesco') === 'Padre' ? 'selected' : '' }}>Padre</option>
                        <option value="Madre" {{ old('resp_parentesco') === 'Madre' ? 'selected' : '' }}>Madre</option>
                        <option value="Tutor Legal" {{ old('resp_parentesco') === 'Tutor Legal' ? 'selected' : '' }}>Tutor Legal</option>
                        <option value="Cónyuge" {{ old('resp_parentesco') === 'Cónyuge' ? 'selected' : '' }}>Cónyuge</option>
                        <option value="Hijo/a" {{ old('resp_parentesco') === 'Hijo/a' ? 'selected' : '' }}>Hijo/a</option>
                        <option value="Otro" {{ old('resp_parentesco') === 'Otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Teléfono del Tutor *</label>
                    <input type="text" name="resp_telefono" class="form-input" value="{{ old('resp_telefono') }}" placeholder="Ej. 70011223">
                </div>
                <div>
                    <label class="form-label">Vigencia de Autorización *</label>
                    <input type="date" name="resp_vigencia" class="form-input" value="{{ old('resp_vigencia', now()->addYear()->toDateString()) }}" min="{{ date('Y-m-d') }}">
                </div>
            </div>
            <small style="color: var(--color-text-muted); display: block; margin-top: 0.5rem;">
                * La autorización requiere una fecha límite de vigencia conforme a los requerimientos normativos del CU7.
            </small>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
            <a href="{{ route('pacientes.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="padding: 0.6rem 2rem;">
                Guardar Ficha del Paciente
            </button>
        </div>
    </form>
</div>

<script>
function toggleResponsableFields() {
    const isChecked = document.getElementById('toggleResponsable').checked;
    const container = document.getElementById('responsableFields');
    container.style.display = isChecked ? 'block' : 'none';
}
</script>
@endsection
