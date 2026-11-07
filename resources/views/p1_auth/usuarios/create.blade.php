@extends('layouts.app')

@section('content')
<div style="max-width: 820px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
                Registrar Nuevo Usuario (CU2)
            </h1>
            <p id="lblInstruccionRol" style="color: var(--color-text-muted); font-size: var(--font-sm);">
                Selecciona el rol asignado para desplegar el formulario unificado correspondiente
            </p>
        </div>
        <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">
            ← Volver al Listado
        </a>
    </div>

    @if($errors->any())
        <div class="card" style="background: var(--color-danger-bg); border-color: var(--color-danger); color: var(--color-danger); padding: 0.85rem 1.25rem; margin-bottom: 1.5rem;">
            <div style="font-weight: 700; margin-bottom: 0.35rem;">Corrige los siguientes errores en el formulario:</div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="padding: 1.75rem;">
        <form action="{{ route('usuarios.store') }}" method="POST" id="formCrearUsuario">
            @csrf

            <!-- Selección de Rol y Estado Inicial -->
            <div style="background: var(--color-surface-hover); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem;">
                    <div>
                        <label class="form-label" for="rol_id" style="font-weight: 700;">Rol Asignado en el Sistema (RBAC) *</label>
                        <select name="rol_id" id="rol_id" class="form-input" required onchange="handleRolChange()">
                            <option value="">-- Selecciona un rol para el usuario --</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}" {{ old('rol_id') == $r->id ? 'selected' : '' }}>
                                    {{ $r->nombre }} &mdash; {{ $r->descripcion }}
                                </option>
                            @endforeach
                        </select>
                        <small style="color: var(--color-text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                            Al elegir "Paciente" o "Médico" se solicitarán todos los datos clínicos o profesionales unificados.
                        </small>
                    </div>
                    <div>
                        <label class="form-label" for="estado" style="font-weight: 700;">Estado Inicial *</label>
                        <select name="estado" id="estado" class="form-input" required>
                            <option value="activo" {{ old('estado', 'activo') === 'activo' ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ old('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- CONTENEDOR 1: FORMULARIO COMPLETO DE PACIENTE (CU7) -->
            <div id="contenedorPaciente" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 2px solid var(--color-primary); padding-bottom: 0.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Ficha de Registro de Nuevo Paciente (CU7)
                    </h3>
                    <span style="font-size: 0.75rem; background: var(--color-surface-hover); color: var(--color-text-muted); padding: 0.25rem 0.6rem; border-radius: 4px; border: 1px solid var(--color-border);">
                        Expediente Clínico Digital
                    </span>
                </div>

                <!-- Sección 1.1: Identificación y Datos Personales -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.75rem 0; border-bottom: 1px solid var(--color-border); padding-bottom: 0.35rem;">
                    1. Datos Personales y de Identificación
                </h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">Cédula de Identidad (CI) *</label>
                        <input type="text" name="ci" id="pac_ci" class="form-input" value="{{ old('ci') }}" placeholder="Ej: 8472910" required>
                    </div>
                    <div>
                        <label class="form-label">Fecha de Nacimiento *</label>
                        <input type="date" name="fecha_nacimiento" id="pac_fecha_nacimiento" class="form-input" value="{{ old('fecha_nacimiento') }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">Nombres Completos *</label>
                        <input type="text" name="nombres" id="pac_nombres" class="form-input" value="{{ old('nombres') }}" placeholder="Ej: Carlos Eduardo" required>
                    </div>
                    <div>
                        <label class="form-label">Apellidos *</label>
                        <input type="text" name="apellidos" id="pac_apellidos" class="form-input" value="{{ old('apellidos') }}" placeholder="Ej: Gómez Salazar" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label class="form-label">Género</label>
                        <select name="genero" id="pac_genero" class="form-input">
                            <option value="">Seleccione género...</option>
                            <option value="Masculino" {{ old('genero') === 'Masculino' ? 'selected' : '' }}>Masculino</option>
                            <option value="Femenino" {{ old('genero') === 'Femenino' ? 'selected' : '' }}>Femenino</option>
                            <option value="Otro" {{ old('genero') === 'Otro' ? 'selected' : '' }}>Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Grupo Sanguíneo</label>
                        <select name="grupo_sanguineo" id="pac_grupo_sanguineo" class="form-input">
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

                <!-- Sección 1.2: Contacto y Residencia -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.75rem 0; border-bottom: 1px solid var(--color-border); padding-bottom: 0.35rem;">
                    2. Contacto y Residencia
                </h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">Teléfono / Celular *</label>
                        <input type="text" name="telefono" id="pac_telefono" class="form-input" value="{{ old('telefono') }}" placeholder="Ej: 77012345" required>
                    </div>
                    <div>
                        <label class="form-label" id="pac_email_label">Correo Electrónico {{ old('tiene_responsable') ? '' : '(Login)' }} *</label>
                        <input type="email" name="email" id="pac_email" class="form-input" value="{{ old('email') }}" placeholder="paciente@correo.com" required>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label class="form-label">Dirección Domiciliaria</label>
                    <input type="text" name="direccion" id="pac_direccion" class="form-input" value="{{ old('direccion') }}" placeholder="Av. San Martín #450, Barrio Equipetrol">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label class="form-label">Contacto de Emergencia (Nombre)</label>
                        <input type="text" name="contacto_emergencia_nombre" id="pac_contacto_emergencia_nombre" class="form-input" value="{{ old('contacto_emergencia_nombre') }}" placeholder="Ej: María Elena Salazar">
                    </div>
                    <div>
                        <label class="form-label">Contacto de Emergencia (Teléfono)</label>
                        <input type="text" name="contacto_emergencia_telefono" id="pac_contacto_emergencia_telefono" class="form-input" value="{{ old('contacto_emergencia_telefono') }}" placeholder="Ej: 77998877">
                    </div>
                </div>

                <!-- Sección 1.3: Responsable Autorizado / Tutor (CU7) -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 0.35rem; margin-bottom: 0.75rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0;">
                        3. Responsable Autorizado / Tutor (Menores o tutelados)
                    </h4>
                    <label style="font-size: 0.85rem; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                        <input type="checkbox" name="tiene_responsable" id="pac_tiene_responsable" value="1" {{ old('tiene_responsable') ? 'checked' : '' }} onchange="toggleTutorFields()">
                        ¿Tiene tutor legal?
                    </label>
                </div>

                <div id="pacTutorFields" style="display: {{ old('tiene_responsable') ? 'block' : 'none' }}; background: var(--color-surface-hover); padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; border: 1px dashed var(--color-border);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                        <div>
                            <label class="form-label">Cédula del Tutor / Responsable *</label>
                            <input type="text" name="resp_ci" id="pac_resp_ci" class="form-input" value="{{ old('resp_ci') }}" placeholder="Ej: 3481902">
                        </div>
                        <div>
                            <label class="form-label">Nombre Completo del Tutor *</label>
                            <input type="text" name="resp_nombres" id="pac_resp_nombres" class="form-input" value="{{ old('resp_nombres') }}" placeholder="Ej: Roberto Gómez">
                        </div>
                        <div>
                            <label class="form-label">Correo Electrónico del Tutor *</label>
                            <input type="email" name="resp_email" id="pac_resp_email" class="form-input" value="{{ old('resp_email') }}" placeholder="Ej: roberto.gomez@gmail.com">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">Parentesco *</label>
                            <select name="resp_parentesco" id="pac_resp_parentesco" class="form-input">
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
                            <input type="text" name="resp_telefono" id="pac_resp_telefono" class="form-input" value="{{ old('resp_telefono') }}" placeholder="Ej: 70011223">
                        </div>
                        <div>
                            <label class="form-label">Vigencia de Autorización *</label>
                            <input type="date" name="resp_vigencia" id="pac_resp_vigencia" class="form-input" value="{{ old('resp_vigencia', now()->addYear()->toDateString()) }}" min="{{ date('Y-m-d') }}">
                        </div>
                    </div>
                </div>

                <!-- Sección 1.4: Contraseña del Paciente (Opcional / C.I. Automática) -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.5rem 0; border-bottom: 1px solid var(--color-border); padding-bottom: 0.35rem;">
                    4. Credenciales de Acceso
                </h4>

                <div style="background: rgba(13, 124, 124, 0.04); border-left: 3px solid var(--color-secondary); padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.85rem; color: var(--color-text-muted);">
                    ℹ️ <strong>Contraseña inicial por defecto:</strong> Si dejas los campos de contraseña vacíos, se asignará automáticamente su <strong>Cédula de Identidad (CI)</strong> como clave inicial y se le solicitará cambiarla obligatoriamente en su primer inicio de sesión.
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label class="form-label" for="pac_password">Contraseña Personalizada (Opcional)</label>
                        <input type="password" name="password" id="pac_password" class="form-input" placeholder="••••••••">
                        <small style="color: var(--color-text-muted); font-size: 0.75rem;">Mayúscula, minúscula, número y símbolo.</small>
                    </div>
                    <div>
                        <label class="form-label" for="pac_password_confirmation">Confirmar Contraseña</label>
                        <input type="password" name="password_confirmation" id="pac_password_confirmation" class="form-input" placeholder="••••••••">
                    </div>
                </div>
            </div>

            <!-- CONTENEDOR 2: FORMULARIO COMPLETO DE MÉDICO (CU4) -->
            <div id="contenedorMedico" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 2px solid var(--color-primary); padding-bottom: 0.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        Registrar Nuevo Médico y Perfil Profesional (CU4)
                    </h3>
                    <span style="font-size: 0.75rem; background: var(--color-surface-hover); color: var(--color-text-muted); padding: 0.25rem 0.6rem; border-radius: 4px; border: 1px solid var(--color-border);">
                        Horario Base Automático
                    </span>
                </div>

                <!-- Sección 2.1: Especialidad y Registro -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.75rem 0; border-bottom: 1px solid var(--color-border); padding-bottom: 0.35rem;">
                    1. Información Profesional y Especialidad
                </h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" for="med_especialidad_id">Especialidad Asignada *</label>
                        <select name="especialidad_id" id="med_especialidad_id" class="form-input" required>
                            <option value="">Selecciona especialidad...</option>
                            @foreach($especialidades as $esp)
                                <option value="{{ $esp->id }}" {{ old('especialidad_id') == $esp->id ? 'selected' : '' }}>
                                    {{ $esp->nombre }} ({{ $esp->codigo }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="med_nro_registro_medico">Nro. Registro / Matrícula Profesional *</label>
                        <input type="text" name="nro_registro_medico" id="med_nro_registro_medico" class="form-input" value="{{ old('nro_registro_medico') }}" placeholder="Ej: COL-MED-12345" required>
                    </div>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" for="med_biografia">Perfil Profesional / Biografía</label>
                    <textarea name="biografia" id="med_biografia" class="form-input" rows="2" placeholder="Estudios de especialidad, años de experiencia clínica...">{{ old('biografia') }}</textarea>
                </div>

                <!-- Sección 2.2: Cuenta de Acceso y Datos Personales -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.75rem 0; border-bottom: 1px solid var(--color-border); padding-bottom: 0.35rem;">
                    2. Cuenta de Acceso y Datos Personales
                </h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" for="med_name">Nombre y Apellidos del Médico *</label>
                        <input type="text" name="name" id="med_name" class="form-input" value="{{ old('name') }}" placeholder="Ej: Dr. Alejandro Ortiz" required>
                    </div>
                    <div>
                        <label class="form-label" for="med_ci">Cédula de Identidad (CI) *</label>
                        <input type="text" name="ci" id="med_ci" class="form-input" value="{{ old('ci') }}" placeholder="Ej: 8940219" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" for="med_email">Correo Electrónico (Login) *</label>
                        <input type="email" name="email" id="med_email" class="form-input" value="{{ old('email') }}" placeholder="dr.ortiz@medicita.com" required>
                    </div>
                    <div>
                        <label class="form-label" for="med_telefono">Teléfono / Celular de Contacto</label>
                        <input type="text" name="telefono" id="med_telefono" class="form-input" value="{{ old('telefono') }}" placeholder="Ej: 71239845">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label class="form-label" for="med_password">Contraseña de Acceso *</label>
                        <input type="password" name="password" id="med_password" class="form-input" placeholder="••••••••" required>
                        <small style="color: var(--color-text-muted); font-size: 0.75rem;">Mayúscula, minúscula, número y símbolo.</small>
                    </div>
                    <div>
                        <label class="form-label" for="med_password_confirmation">Confirmar Contraseña *</label>
                        <input type="password" name="password_confirmation" id="med_password_confirmation" class="form-input" placeholder="Repite la contraseña" required>
                    </div>
                </div>

                <div style="background: rgba(13, 124, 124, 0.04); border-left: 3px solid var(--color-primary); padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--color-text-muted);">
                    🕒 Al registrar al médico, el sistema creará automáticamente su <strong>horario base semanal</strong> (Lunes a Viernes de 08:00 a 17:00 con 1 hora de descanso estricto).
                </div>
            </div>

            <!-- CONTENEDOR 3: FORMULARIO GENERAL (ADMIN, RECEPCIONISTA, LAB) -->
            <div id="contenedorGeneral" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 2px solid var(--color-primary); padding-bottom: 0.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Datos de la Cuenta de Usuario (CU2)
                    </h3>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" for="gen_name">Nombre Completo *</label>
                        <input type="text" name="name" id="gen_name" class="form-input" value="{{ old('name') }}" placeholder="Ej: Lic. Elena Morales" required>
                    </div>
                    <div>
                        <label class="form-label" for="gen_ci">Cédula de Identidad (CI) *</label>
                        <input type="text" name="ci" id="gen_ci" class="form-input" value="{{ old('ci') }}" placeholder="Ej: 8492019" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" for="gen_email">Correo Electrónico *</label>
                        <input type="email" name="email" id="gen_email" class="form-input" value="{{ old('email') }}" placeholder="usuario@medicita.com" required>
                    </div>
                    <div>
                        <label class="form-label" for="gen_telefono">Teléfono / Celular</label>
                        <input type="text" name="telefono" id="gen_telefono" class="form-input" value="{{ old('telefono') }}" placeholder="Ej: 71234567">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label class="form-label" for="gen_password">Contraseña *</label>
                        <input type="password" name="password" id="gen_password" class="form-input" placeholder="••••••••" required>
                        <small style="color: var(--color-text-muted); font-size: 0.75rem;">Mayúscula, minúscula, número y símbolo.</small>
                    </div>
                    <div>
                        <label class="form-label" for="gen_password_confirmation">Confirmar Contraseña *</label>
                        <input type="password" name="password_confirmation" id="gen_password_confirmation" class="form-input" placeholder="Repite la contraseña" required>
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
                <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" id="btnGuardarUsuario" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-size: 0.95rem; font-weight: 600;">
                    Guardar Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const rolesMap = {
    @foreach($roles as $r)
        "{{ $r->id }}": "{{ $r->slug }}",
    @endforeach
};

function setContainerActive(container, active) {
    if (!container) return;
    container.style.display = active ? 'block' : 'none';
    const inputs = container.querySelectorAll('input, select, textarea');
    inputs.forEach(el => {
        el.disabled = !active;
    });
}

function toggleTutorFields() {
    const chk = document.getElementById('pac_tiene_responsable');
    const container = document.getElementById('pacTutorFields');
    const emailLbl = document.getElementById('pac_email_label');
    if (chk && container) {
        container.style.display = chk.checked ? 'block' : 'none';
        if (emailLbl) {
            emailLbl.innerText = chk.checked ? 'Correo Electrónico *' : 'Correo Electrónico (Login) *';
        }
        ['pac_resp_ci', 'pac_resp_nombres', 'pac_resp_email', 'pac_resp_telefono', 'pac_resp_vigencia'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                if (chk.checked) el.setAttribute('required', 'required');
                else el.removeAttribute('required');
            }
        });
    }
}

function handleRolChange() {
    const rolSelect = document.getElementById('rol_id');
    const slug = rolesMap[rolSelect.value] || '';

    const contPac = document.getElementById('contenedorPaciente');
    const contMed = document.getElementById('contenedorMedico');
    const contGen = document.getElementById('contenedorGeneral');
    const lblInstruccion = document.getElementById('lblInstruccionRol');
    const btnGuardar = document.getElementById('btnGuardarUsuario');

    setContainerActive(contPac, slug === 'paciente');
    setContainerActive(contMed, slug === 'medico');
    setContainerActive(contGen, slug !== 'paciente' && slug !== 'medico' && slug !== '');

    if (slug === 'paciente') {
        if (lblInstruccion) lblInstruccion.innerText = 'Ficha de Nuevo Paciente (CU7): se creará la cuenta y su expediente clínico digital.';
        if (btnGuardar) btnGuardar.innerText = 'Guardar Paciente y Cuenta';
        toggleTutorFields();
    } else if (slug === 'medico') {
        if (lblInstruccion) lblInstruccion.innerText = 'Registro de Nuevo Médico (CU4): se creará la cuenta, perfil profesional y horario base.';
        if (btnGuardar) btnGuardar.innerText = 'Guardar Médico y Cuenta';
    } else if (slug) {
        if (lblInstruccion) lblInstruccion.innerText = 'Registro de Usuario del Sistema (CU2): credenciales y rol RBAC asignado.';
        if (btnGuardar) btnGuardar.innerText = 'Guardar Usuario';
    } else {
        if (lblInstruccion) lblInstruccion.innerText = 'Selecciona el rol asignado para desplegar el formulario unificado correspondiente.';
        if (btnGuardar) btnGuardar.innerText = 'Guardar Usuario';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    handleRolChange();
});
</script>
@endsection
