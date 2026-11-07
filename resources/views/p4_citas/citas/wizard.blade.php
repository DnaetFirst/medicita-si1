@extends('layouts.app')

@section('content')
<div style="max-width: 980px; margin: 0 auto;">
    <!-- Encabezado del Asistente -->
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin: 0;">
                Solicitud de Ficha y Reserva de Citas (CU8)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin: 0.25rem 0 0 0;">
                Asistente guiado de 4 pasos con asignación de turnos de 15 minutos y confirmación en tiempo real
            </p>
        </div>
        <a href="{{ auth()->user()->isPaciente() ? route('citas.mis_citas') : route('citas.index') }}" class="btn btn-secondary">
            &larr; {{ auth()->user()->isPaciente() ? 'Mis Citas' : 'Volver al Tablero' }}
        </a>
    </div>

    <!-- Stepper Visual -->
    <div class="card" style="padding: 1rem 1.5rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; position: relative;">
            <div style="position: absolute; top: 18px; left: 10%; right: 10%; height: 2px; background: var(--color-border); z-index: 1;"></div>
            
            <div class="step-indicator active" id="stepIndicator1" onclick="irAlPaso(1)" style="position: relative; z-index: 2; text-align: center; cursor: pointer; flex: 1;">
                <div class="step-circle" style="width: 36px; height: 36px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.25rem; font-weight: 700; font-size: 0.9rem;">1</div>
                <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-primary);">Especialidad</div>
            </div>

            <div class="step-indicator" id="stepIndicator2" onclick="irAlPaso(2)" style="position: relative; z-index: 2; text-align: center; cursor: pointer; flex: 1;">
                <div class="step-circle" style="width: 36px; height: 36px; border-radius: 50%; background: var(--color-surface-hover); border: 2px solid var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; margin: 0 auto 0.25rem; font-weight: 700; font-size: 0.9rem;">2</div>
                <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted);">Médico & Día</div>
            </div>

            <div class="step-indicator" id="stepIndicator3" onclick="irAlPaso(3)" style="position: relative; z-index: 2; text-align: center; cursor: pointer; flex: 1;">
                <div class="step-circle" style="width: 36px; height: 36px; border-radius: 50%; background: var(--color-surface-hover); border: 2px solid var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; margin: 0 auto 0.25rem; font-weight: 700; font-size: 0.9rem;">3</div>
                <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted);">Turno (15m)</div>
            </div>

            <div class="step-indicator" id="stepIndicator4" onclick="irAlPaso(4)" style="position: relative; z-index: 2; text-align: center; cursor: pointer; flex: 1;">
                <div class="step-circle" style="width: 36px; height: 36px; border-radius: 50%; background: var(--color-surface-hover); border: 2px solid var(--color-border); color: var(--color-text-muted); display: flex; align-items: center; justify-content: center; margin: 0 auto 0.25rem; font-weight: 700; font-size: 0.9rem;">4</div>
                <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted);">Confirmación</div>
            </div>
        </div>
    </div>

    <!-- Formulario Principal -->
    <form id="wizardForm" method="POST" action="{{ route('citas.store') }}">
        @csrf
        @if($derivacion)<input type="hidden" name="derivacion_id" value="{{ $derivacion->id }}"><p class="card cycle-card">Reserva asociada a la derivación #{{ $derivacion->id }}. El paciente y la especialidad se verificarán al confirmar.</p>@endif
        <input type="hidden" name="operacion_id" value="{{ old('operacion_id', (string) Str::uuid()) }}">
        <input type="hidden" name="especialidad_id" id="hiddenEspecialidadId" value="{{ $selectedEspecialidadId }}">
        <input type="hidden" name="medico_id" id="hiddenMedicoId" value="{{ $selectedMedicoId }}">
        <input type="hidden" name="slot_id" id="hiddenSlotId" value="">
        <input type="hidden" name="fecha" id="hiddenFecha" value="{{ $selectedFecha }}">

        <!-- PASO 1: Selección de Especialidad -->
        <div id="step1" class="wizard-step card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-primary); margin-bottom: 0.5rem;">
                Paso 1: Selecciona la Especialidad Médica
            </h3>
            <p style="color: var(--color-text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
                Elige el área clínica requerida para filtrar los médicos especialistas disponibles
            </p>

            <div class="grid-3" style="gap: 1rem;">
                @foreach($especialidades as $esp)
                    <div class="especialidad-card card" onclick="seleccionarEspecialidad({{ $esp->id }}, '{{ addslashes($esp->nombre) }}')" id="cardEsp{{ $esp->id }}" style="margin: 0; padding: 1.25rem; cursor: pointer; transition: all 0.2s ease; border: 2px solid {{ $selectedEspecialidadId == $esp->id ? 'var(--color-primary)' : 'var(--color-border)' }}; {{ $selectedEspecialidadId == $esp->id ? 'background: rgba(13,124,124,0.04);' : '' }}">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <span style="width: 14px; height: 14px; border-radius: 50%; background: {{ $esp->color ?? 'var(--color-primary)' }}; display: inline-block;"></span>
                            <h4 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--color-primary);">{{ $esp->nombre }}</h4>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--color-text-muted); margin: 0 0 0.75rem 0; line-height: 1.4;">
                            {{ Str::limit($esp->descripcion, 70) }}
                        </p>
                        <div style="font-size: 0.75rem; color: var(--color-secondary); font-weight: 600;">
                            {{ $esp->medicos_count }} {{ Str::plural('médico activo', $esp->medicos_count) }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="btn btn-primary" onclick="avanzarPaso(2)">
                    Continuar al Paso 2 &rarr;
                </button>
            </div>
        </div>

        <!-- PASO 2: Selección de Médico y Fecha -->
        <div id="step2" class="wizard-step card" style="display: none; padding: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-primary); margin-bottom: 0.5rem;">
                Paso 2: Selecciona el Médico y la Fecha de Atención
            </h3>
            <p style="color: var(--color-text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
                Especialidad seleccionada: <strong id="lblEspecialidadSeleccionada" style="color: var(--color-primary);"></strong>
            </p>

            <div class="grid-2" style="gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label class="form-label" style="font-weight: 700;">1. Selecciona el Profesional Médico *</label>
                    <div id="listaMedicosContainer" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <p style="color: var(--color-text-muted); font-size: 0.875rem;">Cargando médicos disponibles...</p>
                    </div>
                </div>

                <div>
                    <label class="form-label" style="font-weight: 700;">2. Selecciona la Fecha Deseada *</label>
                    <input type="date" id="inputFecha" class="form-input" value="{{ $selectedFecha }}" min="{{ date('Y-m-d') }}" onchange="cambioFecha()" style="width: 100%; margin-bottom: 0.75rem; font-size: 1rem; font-weight: 600;">
                    <div style="background: var(--color-surface-hover); padding: 0.85rem; border-radius: var(--radius-md); font-size: 0.8rem; color: var(--color-text-muted); border-left: 3px solid var(--color-secondary);">
                        Atención médica de lunes a sábado según la matriz horaria individual de cada médico con 1 hora de descanso estricto.
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="avanzarPaso(1)">
                    &larr; Volver
                </button>
                <button type="button" class="btn btn-primary" onclick="avanzarPaso(3)">
                    Ver Turnos Disponibles &rarr;
                </button>
            </div>
        </div>

        <!-- PASO 3: Selección de Slot / Turno (15 min) -->
        <div id="step3" class="wizard-step card" style="display: none; padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-primary); margin: 0;">
                    Paso 3: Selecciona tu Turno de Consulta (15 min)
                </h3>
                <div style="display: flex; gap: 0.5rem; font-size: 0.75rem;">
                    <span style="display: flex; align-items: center; gap: 0.25rem;"><span style="width: 10px; height: 10px; background: #10b981; border-radius: 2px;"></span> Disponible</span>
                    <span style="display: flex; align-items: center; gap: 0.25rem;"><span style="width: 10px; height: 10px; background: #f59e0b; border-radius: 2px;"></span> Descanso (1h)</span>
                    <span style="display: flex; align-items: center; gap: 0.25rem;"><span style="width: 10px; height: 10px; background: #ef4444; border-radius: 2px;"></span> Ocupado</span>
                </div>
            </div>
            <p style="color: var(--color-text-muted); font-size: 0.875rem; margin-bottom: 1.25rem;">
                Médico: <strong id="lblResumenMedico"></strong> &bull; Fecha: <strong id="lblResumenFecha"></strong>
            </p>

            <!-- Grid reactivo de slots -->
            <div id="slotsLoading" style="display: none; text-align: center; padding: 2rem;">
                <p style="color: var(--color-primary); font-weight: 600;">Consultando agenda y slots en tiempo real...</p>
            </div>

            <div id="slotsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(105px, 1fr)); gap: 0.6rem; max-height: 380px; overflow-y: auto; padding: 0.5rem 0.25rem;">
                <!-- Rellenado dinámicamente con JS vía /api/agenda/slots -->
            </div>

            <div id="slotSeleccionadoAlert" style="display: none; margin-top: 1rem; background: #ecfdf5; border-left: 4px solid #10b981; padding: 0.85rem; border-radius: var(--radius-md); font-size: 0.9rem; color: #065f46;">
                Has seleccionado el turno de las <strong id="lblHoraSlotSeleccionado"></strong>. Haz clic en continuar para ingresar los datos del paciente.
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="avanzarPaso(2)">
                    &larr; Volver
                </button>
                <button type="button" class="btn btn-primary" onclick="avanzarPaso(4)">
                    Continuar al Paciente &rarr;
                </button>
            </div>
        </div>

        <!-- PASO 4: Paciente y Confirmación -->
        <div id="step4" class="wizard-step card" style="display: none; padding: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-primary); margin-bottom: 0.5rem;">
                Paso 4: Asignación de Paciente y Confirmación de Ficha
            </h3>
            <p style="color: var(--color-text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
                Vincula el paciente a la cita o registra uno nuevo si es primera consulta
            </p>

            <!-- Alerta si el paciente ya tiene una ficha activa -->
            <div id="alertaFichaActiva" style="display: {{ $fichaActiva ? 'block' : 'none' }}; background: #fef2f2; border: 2px solid #ef4444; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                <div style="display: flex; gap: 0.85rem; align-items: flex-start;">
                    <span style="font-size: 1.5rem; line-height: 1;">⛔</span>
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 0.35rem 0; color: #991b1b; font-size: 1rem; font-weight: 700;">
                            El paciente ya cuenta con una ficha médica activa
                        </h4>
                        <p id="alertaFichaActivaTexto" style="margin: 0 0 0.5rem 0; color: #7f1d1d; font-size: 0.875rem; line-height: 1.4;">
                            @if($fichaActiva)
                                Ya existe la ficha activa <strong>{{ $fichaActiva->codigo_ficha }}</strong> programada para el <strong>{{ $fichaActiva->fecha ? $fichaActiva->fecha->format('d/m/Y') : '' }}</strong> a las <strong>{{ substr($fichaActiva->hora ?? '', 0, 5) }}</strong> con <strong>{{ $fichaActiva->medico?->user?->name ?? 'el especialista asignado' }}</strong> ({{ $fichaActiva->medico?->especialidad?->nombre ?? '' }}).
                            @endif
                        </p>
                        <div style="font-size: 0.8rem; color: #991b1b; background: rgba(239, 68, 68, 0.08); padding: 0.5rem 0.75rem; border-radius: 4px;">
                            <strong>Regla de emisión de fichas:</strong> No es posible sacar otra ficha médica mientras exista una ficha activa en curso. Debe esperar a que concluya su atención o gestionar/reprogramar su cita existente.
                        </div>
                        @if(auth()->user()->isPaciente())
                            <div style="margin-top: 0.75rem;">
                                <a href="{{ route('citas.mis_citas') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                                    &larr; Ver Mis Citas Activas
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if(auth()->user()->isPaciente())
                <label class="form-label" style="font-weight: 700;">Paciente para quien se reserva</label>
                <select name="paciente_id" id="selectPacienteRolPaciente" class="form-input" required onchange="cambioPacienteRolPaciente(this.value)">
                    @foreach($pacientes as $pac)
                        @php
                            $activa = $pac->citas->first();
                        @endphp
                        <option value="{{ $pac->id }}" @selected(old('paciente_id', $selectedPacienteId) == $pac->id)>
                            {{ $pac->nombre_completo }} {{ $activa ? '⚠️ (Ficha activa: ' . $activa->codigo_ficha . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            @else
                <!-- Toggle nuevo / existente -->
                <div style="margin-bottom: 1.25rem; display: flex; gap: 1.5rem; background: var(--color-surface-hover); padding: 0.75rem 1rem; border-radius: var(--radius-md);">
                    <label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                        <input type="radio" name="tipo_paciente" value="existente" checked onchange="toggleTipoPaciente('existente')">
                        Paciente Registrado en Sistema
                    </label>
                    <label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                        <input type="radio" name="tipo_paciente" value="nuevo" onchange="toggleTipoPaciente('nuevo')">
                        Nuevo Paciente (Alta Rápida)
                    </label>
                </div>

                <!-- Paciente Existente -->
                <div id="seccionPacienteExistente" style="margin-bottom: 1.5rem; position: relative;">
                    <label class="form-label" for="inputBuscarPaciente">Selecciona o Busca el Paciente *</label>
                    <div style="position: relative;">
                        <input type="text"
                               id="inputBuscarPaciente"
                               class="form-input"
                               placeholder="Escribe el nombre, CI o teléfono del paciente..."
                               autocomplete="off"
                               style="width: 100%; font-size: 0.95rem; padding-right: 2.5rem;"
                               value="{{ $pacientePreseleccionado ? $pacientePreseleccionado->nombre_completo . ' — CI: ' . $pacientePreseleccionado->ci : '' }}">
                        
                        <button type="button"
                                id="btnLimpiarPaciente"
                                onclick="limpiarSeleccionPaciente()"
                                style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1.25rem; color: var(--color-text-muted); cursor: pointer; display: {{ $pacientePreseleccionado ? 'block' : 'none' }}; line-height: 1;"
                                title="Borrar y buscar otro paciente">&times;</button>
                        
                        <input type="hidden" name="paciente_id" id="selectPaciente" value="{{ old('paciente_id', $selectedPacienteId) }}">

                        <!-- Dropdown de resultados filtrados -->
                        <div id="dropdownPacientes" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; max-height: 250px; overflow-y: auto; background: white; border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1000;">
                        </div>
                    </div>
                    <small style="color: var(--color-text-muted); font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                        Escribe en este mismo campo el nombre, apellido, CI o celular para filtrar la lista instantáneamente.
                    </small>
                    <div id="pacienteSeleccionadoInfo" style="display: {{ $pacientePreseleccionado ? 'flex' : 'none' }}; margin-top: 0.5rem; font-size: 0.85rem; color: #065f46; background: #ecfdf5; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid #a7f3d0; align-items: center; justify-content: space-between;">
                        <span>✓ Paciente vinculado: <strong id="lblPacienteVinculado">{{ $pacientePreseleccionado ? $pacientePreseleccionado->nombre_completo . ' (CI: ' . $pacientePreseleccionado->ci . ')' : '' }}</strong></span>
                    </div>
                </div>

                <!-- Nuevo Paciente Inline: Formulario Completo de Paciente (CU7) -->
                <input type="hidden" name="crear_paciente_nuevo" id="crearPacienteNuevo" value="0">
                <div id="seccionPacienteNuevo" style="display: none; background: var(--color-surface-hover); padding: 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px dashed var(--color-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 1.05rem; color: var(--color-primary); font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Ficha de Registro de Nuevo Paciente (CU7)
                        </h4>
                        <span style="font-size: 0.75rem; color: var(--color-text-muted); background: white; padding: 0.25rem 0.6rem; border-radius: 4px; border: 1px solid var(--color-border);">
                            * Campos obligatorios
                        </span>
                    </div>

                    <!-- Sección 1: Datos Personales y de Identificación -->
                    <h5 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.75rem 0; border-bottom: 1px solid var(--color-border); padding-bottom: 0.4rem;">
                        1. Datos Personales y de Identificación
                    </h5>

                    <div class="grid-2" style="margin-bottom: 0.75rem;">
                        <div>
                            <label class="form-label">Cédula de Identidad (CI) *</label>
                            <input type="text" name="nuevo_ci" id="nuevo_ci" class="form-input" placeholder="Ej. 8472910" value="{{ old('nuevo_ci') }}">
                        </div>
                        <div>
                            <label class="form-label">Fecha de Nacimiento *</label>
                            <input type="date" name="nuevo_fecha_nacimiento" id="nuevo_fecha_nacimiento" class="form-input" max="{{ date('Y-m-d') }}" value="{{ old('nuevo_fecha_nacimiento') }}">
                        </div>
                    </div>

                    <div class="grid-2" style="margin-bottom: 0.75rem;">
                        <div>
                            <label class="form-label">Nombres Completos *</label>
                            <input type="text" name="nuevo_nombres" id="nuevo_nombres" class="form-input" placeholder="Ej. Carlos Eduardo" value="{{ old('nuevo_nombres') }}">
                        </div>
                        <div>
                            <label class="form-label">Apellidos *</label>
                            <input type="text" name="nuevo_apellidos" id="nuevo_apellidos" class="form-input" placeholder="Ej. Gómez Salazar" value="{{ old('nuevo_apellidos') }}">
                        </div>
                    </div>

                    <div class="grid-2" style="margin-bottom: 1.25rem;">
                        <div>
                            <label class="form-label">Género</label>
                            <select name="nuevo_genero" id="nuevo_genero" class="form-input">
                                <option value="">Seleccione género...</option>
                                <option value="Masculino" {{ old('nuevo_genero') === 'Masculino' ? 'selected' : '' }}>Masculino</option>
                                <option value="Femenino" {{ old('nuevo_genero') === 'Femenino' ? 'selected' : '' }}>Femenino</option>
                                <option value="Otro" {{ old('nuevo_genero') === 'Otro' ? 'selected' : '' }}>Otro</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Grupo Sanguíneo</label>
                            <select name="nuevo_grupo_sanguineo" id="nuevo_grupo_sanguineo" class="form-input">
                                <option value="">Seleccione grupo...</option>
                                <option value="O+" {{ old('nuevo_grupo_sanguineo') === 'O+' ? 'selected' : '' }}>O+</option>
                                <option value="O-" {{ old('nuevo_grupo_sanguineo') === 'O-' ? 'selected' : '' }}>O-</option>
                                <option value="A+" {{ old('nuevo_grupo_sanguineo') === 'A+' ? 'selected' : '' }}>A+</option>
                                <option value="A-" {{ old('nuevo_grupo_sanguineo') === 'A-' ? 'selected' : '' }}>A-</option>
                                <option value="B+" {{ old('nuevo_grupo_sanguineo') === 'B+' ? 'selected' : '' }}>B+</option>
                                <option value="B-" {{ old('nuevo_grupo_sanguineo') === 'B-' ? 'selected' : '' }}>B-</option>
                                <option value="AB+" {{ old('nuevo_grupo_sanguineo') === 'AB+' ? 'selected' : '' }}>AB+</option>
                                <option value="AB-" {{ old('nuevo_grupo_sanguineo') === 'AB-' ? 'selected' : '' }}>AB-</option>
                            </select>
                        </div>
                    </div>

                    <!-- Sección 2: Contacto y Residencia -->
                    <h5 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.75rem 0; border-bottom: 1px solid var(--color-border); padding-bottom: 0.4rem;">
                        2. Contacto y Residencia
                    </h5>

                    <div class="grid-2" style="margin-bottom: 0.75rem;">
                        <div>
                            <label class="form-label">Teléfono / Celular *</label>
                            <input type="text" name="nuevo_telefono" id="nuevo_telefono" class="form-input" placeholder="Ej. 77012345" value="{{ old('nuevo_telefono') }}">
                        </div>
                        <div>
                            <label class="form-label">Correo Electrónico *</label>
                            <input type="email" name="nuevo_email" id="nuevo_email" class="form-input" placeholder="paciente@correo.com" value="{{ old('nuevo_email') }}">
                            <small style="color: var(--color-text-muted); font-size: 0.75rem;">Se creará automáticamente la cuenta con su CI como contraseña inicial.</small>
                        </div>
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <label class="form-label">Dirección Domiciliaria</label>
                        <input type="text" name="nuevo_direccion" id="nuevo_direccion" class="form-input" placeholder="Av. San Martín #450, Barrio Equipetrol" value="{{ old('nuevo_direccion') }}">
                    </div>

                    <div class="grid-2" style="margin-bottom: 1.25rem;">
                        <div>
                            <label class="form-label">Contacto de Emergencia (Nombre)</label>
                            <input type="text" name="nuevo_contacto_emergencia_nombre" id="nuevo_contacto_emergencia_nombre" class="form-input" placeholder="Ej. María Elena Salazar" value="{{ old('nuevo_contacto_emergencia_nombre') }}">
                        </div>
                        <div>
                            <label class="form-label">Contacto de Emergencia (Teléfono)</label>
                            <input type="text" name="nuevo_contacto_emergencia_telefono" id="nuevo_contacto_emergencia_telefono" class="form-input" placeholder="Ej. 77998877" value="{{ old('nuevo_contacto_emergencia_telefono') }}">
                        </div>
                    </div>

                    <!-- Sección 3: Tutor o Responsable Legal Autorizado (CU7) -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 0.4rem; margin-bottom: 0.75rem;">
                        <h5 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0;">
                            3. Responsable Autorizado / Tutor (Menores o tutelados)
                        </h5>
                        <label style="font-size: 0.85rem; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                            <input type="checkbox" name="nuevo_tiene_responsable" id="toggleNuevoResponsable" value="1" {{ old('nuevo_tiene_responsable') ? 'checked' : '' }} onchange="toggleNuevoResponsableFields()">
                            ¿Tiene tutor legal?
                        </label>
                    </div>

                    <div id="nuevoResponsableFields" style="display: {{ old('nuevo_tiene_responsable') ? 'block' : 'none' }}; background: white; padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 0.5rem; border: 1px solid var(--color-border);">
                        <div class="grid-2" style="margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label">Cédula del Tutor / Responsable *</label>
                                <input type="text" name="nuevo_resp_ci" id="nuevo_resp_ci" class="form-input" placeholder="Ej. 3481902" value="{{ old('nuevo_resp_ci') }}">
                            </div>
                            <div>
                                <label class="form-label">Nombre Completo del Tutor *</label>
                                <input type="text" name="nuevo_resp_nombres" id="nuevo_resp_nombres" class="form-input" placeholder="Ej. Roberto Gómez" value="{{ old('nuevo_resp_nombres') }}">
                            </div>
                        </div>

                        <div class="grid-3" style="margin-bottom: 0.5rem;">
                            <div>
                                <label class="form-label">Parentesco *</label>
                                <select name="nuevo_resp_parentesco" id="nuevo_resp_parentesco" class="form-input">
                                    <option value="Padre" {{ old('nuevo_resp_parentesco') === 'Padre' ? 'selected' : '' }}>Padre</option>
                                    <option value="Madre" {{ old('nuevo_resp_parentesco') === 'Madre' ? 'selected' : '' }}>Madre</option>
                                    <option value="Tutor Legal" {{ old('nuevo_resp_parentesco') === 'Tutor Legal' ? 'selected' : '' }}>Tutor Legal</option>
                                    <option value="Cónyuge" {{ old('nuevo_resp_parentesco') === 'Cónyuge' ? 'selected' : '' }}>Cónyuge</option>
                                    <option value="Hijo/a" {{ old('nuevo_resp_parentesco') === 'Hijo/a' ? 'selected' : '' }}>Hijo/a</option>
                                    <option value="Otro" {{ old('nuevo_resp_parentesco') === 'Otro' ? 'selected' : '' }}>Otro</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Teléfono del Tutor *</label>
                                <input type="text" name="nuevo_resp_telefono" id="nuevo_resp_telefono" class="form-input" placeholder="Ej. 70011223" value="{{ old('nuevo_resp_telefono') }}">
                            </div>
                            <div>
                                <label class="form-label">Vigencia de Autorización *</label>
                                <input type="date" name="nuevo_resp_vigencia" id="nuevo_resp_vigencia" class="form-input" value="{{ old('nuevo_resp_vigencia', now()->addYear()->toDateString()) }}" min="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Motivo de consulta -->
            <div style="margin-bottom: 1.5rem;">
                <label class="form-label">Motivo de Consulta o Síntomas Principales</label>
                <textarea name="motivo_consulta" class="form-input" rows="3" placeholder="Ej. Dolor abdominal moderado de 2 días de evolución, control rutinario, etc."></textarea>
            </div>

            <!-- Resumen de Ficha a Emitir -->
            <div style="background: rgba(13, 124, 124, 0.05); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                <h4 style="margin: 0 0 0.5rem 0; color: var(--color-primary); font-size: 0.95rem;">Resumen de la Ficha Médica a Generar</h4>
                <div class="grid-3" style="font-size: 0.85rem;">
                    <div>Especialidad: <strong id="lblConfEsp">-</strong></div>
                    <div>Médico: <strong id="lblConfMed">-</strong></div>
                    <div>Fecha y Hora: <strong id="lblConfFechaHora">-</strong></div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-secondary" onclick="avanzarPaso(3)">
                    &larr; Volver
                </button>
                <button type="submit" id="btnConfirmarCita" class="btn btn-primary" style="padding: 0.75rem 2.5rem; font-size: 1rem; box-shadow: 0 4px 14px rgba(13, 124, 124, 0.25);">
                    Confirmar y Emitir Ficha Médica (CU8)
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let currentStep = 1;
let selectedEspId = {{ $selectedEspecialidadId ?: 'null' }};
let selectedEspName = '';
let selectedMedId = {{ $selectedMedicoId ?: 'null' }};
let selectedMedName = '';
let selectedSlotId = null;
let selectedSlotHora = '';

function irAlPaso(n) {
    if (n === 2 && !selectedEspId) {
        alert('Por favor selecciona primero una Especialidad Médica.');
        return;
    }
    if (n === 3 && (!selectedEspId || !selectedMedId)) {
        alert('Por favor selecciona primero un Médico especialista.');
        return;
    }
    if (n === 4 && (!selectedSlotId)) {
        alert('Por favor selecciona un turno (slot de 15 min) disponible.');
        return;
    }
    avanzarPaso(n);
}

function avanzarPaso(n) {
    if (n === 2 && !selectedEspId) {
        alert('Debes elegir una especialidad médica.');
        return;
    }
    if (n === 3 && !selectedMedId) {
        alert('Debes seleccionar el médico especialista.');
        return;
    }
    if (n === 4 && !selectedSlotId) {
        alert('Debes elegir un turno horario disponible.');
        return;
    }

    // Ocultar paso actual y mostrar nuevo paso
    document.querySelectorAll('.wizard-step').forEach(el => el.style.display = 'none');
    document.getElementById(`step${n}`).style.display = 'block';

    // Actualizar stepper
    for (let i = 1; i <= 4; i++) {
        const ind = document.getElementById(`stepIndicator${i}`);
        const circle = ind.querySelector('.step-circle');
        if (i <= n) {
            circle.style.background = 'var(--color-primary)';
            circle.style.borderColor = 'var(--color-primary)';
            circle.style.color = 'white';
        } else {
            circle.style.background = 'var(--color-surface-hover)';
            circle.style.borderColor = 'var(--color-border)';
            circle.style.color = 'var(--color-text-muted)';
        }
    }

    currentStep = n;

    if (n === 2) {
        cargarMedicosPorEspecialidad();
    } else if (n === 3) {
        cargarSlotsAgenda();
    } else if (n === 4) {
        actualizarResumenFinal();
    }
}

function seleccionarEspecialidad(id, nombre) {
    selectedEspId = id;
    selectedEspName = nombre;
    document.getElementById('hiddenEspecialidadId').value = id;
    document.getElementById('lblEspecialidadSeleccionada').innerText = nombre;

    document.querySelectorAll('.especialidad-card').forEach(c => {
        c.style.border = '2px solid var(--color-border)';
        c.style.background = 'var(--color-surface)';
    });

    const selectedCard = document.getElementById(`cardEsp${id}`);
    if (selectedCard) {
        selectedCard.style.border = '2px solid var(--color-primary)';
        selectedCard.style.background = 'rgba(13,124,124,0.04)';
    }

    selectedMedId = null;
    resetSlot();
    document.getElementById('hiddenMedicoId').value = '';
    avanzarPaso(2);
}

// Cargar médicos de la especialidad vía AJAX o datos
const medicosPorEspecialidad = {
    @foreach($especialidades as $esp)
        "{{ $esp->id }}": [
            @foreach($esp->medicos as $m)
                { id: {{ $m->id }}, nombre: {{ Illuminate\Support\Js::from($m->user?->name) }}, registro: {{ Illuminate\Support\Js::from($m->nro_registro_medico) }} },
            @endforeach
        ],
    @endforeach
};

function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value;
    return element.innerHTML;
}

function cargarMedicosPorEspecialidad() {
    const container = document.getElementById('listaMedicosContainer');
    container.innerHTML = '';

    const medicos = medicosPorEspecialidad[selectedEspId] || [];

    if (medicos.length === 0) {
        container.innerHTML = '<p style="color: var(--color-danger); font-size: 0.85rem;">No hay médicos activos registrados para esta especialidad.</p>';
        return;
    }

    medicos.forEach(m => {
        const div = document.createElement('div');
        div.className = 'card';
        div.style.margin = '0';
        div.style.padding = '0.85rem 1rem';
        div.style.cursor = 'pointer';
        div.style.border = selectedMedId === m.id ? '2px solid var(--color-primary)' : '1px solid var(--color-border)';
        div.style.background = selectedMedId === m.id ? 'rgba(13,124,124,0.04)' : 'var(--color-surface)';
        div.onclick = () => seleccionarMedico(m.id, m.nombre);
        div.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 700; color: var(--color-primary);">${escapeHtml(m.nombre)}</div>
                    <div style="font-size: 0.75rem; color: var(--color-text-muted);">Matrícula: ${escapeHtml(m.registro)}</div>
                </div>
                <span class="badge badge-success">Activo</span>
            </div>
        `;
        container.appendChild(div);
    });
}

function seleccionarMedico(id, nombre) {
    resetSlot();
    selectedMedId = id;
    selectedMedName = nombre;
    document.getElementById('hiddenMedicoId').value = id;
    cargarMedicosPorEspecialidad(); // Refrescar border activo
}

function cambioFecha() {
    resetSlot();
    const fecha = document.getElementById('inputFecha').value;
    document.getElementById('hiddenFecha').value = fecha;
    if (currentStep === 3) {
        cargarSlotsAgenda();
    }
}

let slotsRequest = 0;
function resetSlot() {
    slotsRequest++;
    selectedSlotId = null;
    selectedSlotHora = '';
    document.getElementById('hiddenSlotId').value = '';
    document.getElementById('slotSeleccionadoAlert').style.display = 'none';
}

async function cargarSlotsAgenda() {
    resetSlot();
    const requestId = slotsRequest;
    const fecha = document.getElementById('inputFecha').value;
    document.getElementById('lblResumenMedico').innerText = selectedMedName;
    document.getElementById('lblResumenFecha').innerText = fecha;

    const container = document.getElementById('slotsContainer');
    const loading = document.getElementById('slotsLoading');

    container.innerHTML = '';
    loading.style.display = 'block';

    try {
        const response = await fetch(`/api/agenda/slots?medico_id=${selectedMedId}&fecha=${fecha}`);
        if (!response.ok) throw new Error('No se pudieron cargar los turnos');
        const data = await response.json();
        if (requestId !== slotsRequest) return;
        loading.style.display = 'none';

        if (!data.slots || data.slots.length === 0) {
            container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--color-text-muted);">El médico no tiene jornada de atención programada para esta fecha o no hay turnos disponibles. Elige otra fecha.</div>';
            return;
        }

        data.slots.forEach(slot => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const hora = slot.hora_inicio.substr(0, 5);
            btn.innerText = hora;
            btn.style.padding = '0.65rem 0.4rem';
            btn.style.borderRadius = 'var(--radius-md)';
            btn.style.fontSize = '0.85rem';
            btn.style.fontWeight = '700';
            btn.style.cursor = 'pointer';
            btn.style.border = '1px solid var(--color-border)';

            if (slot.reservable) {
                btn.style.background = '#ecfdf5';
                btn.style.color = '#065f46';
                btn.style.borderColor = '#10b981';
                btn.onclick = () => seleccionarSlot(slot.id, hora, btn);
            } else if (slot.estado === 'descanso') {
                btn.style.background = '#fef3c7';
                btn.style.color = '#92400e';
                btn.style.cursor = 'not-allowed';
                btn.title = 'Período obligatorio de descanso médico (1 hora)';
                btn.disabled = true;
                btn.innerHTML = `${hora}<br><span style="font-size:0.65rem; font-weight:400;">Descanso</span>`;
            } else {
                btn.style.background = '#fee2e2';
                btn.style.color = '#991b1b';
                btn.style.cursor = 'not-allowed';
                btn.title = 'Turno no disponible u ocupado';
                btn.disabled = true;
            }

            container.appendChild(btn);
        });

    } catch (e) {
        if (requestId !== slotsRequest) return;
        loading.style.display = 'none';
        container.innerHTML = '<div style="grid-column: 1 / -1; color: var(--color-danger); text-align: center;">Error al cargar turnos del servidor.</div>';
    }
}

function seleccionarSlot(id, hora, btnElem) {
    selectedSlotId = id;
    selectedSlotHora = hora;
    document.getElementById('hiddenSlotId').value = id;

    // Quitar selección previa
    document.querySelectorAll('#slotsContainer button').forEach(b => {
        if (!b.disabled) {
            b.style.background = '#ecfdf5';
            b.style.color = '#065f46';
            b.style.boxShadow = 'none';
        }
    });

    btnElem.style.background = 'var(--color-primary)';
    btnElem.style.color = 'white';
    btnElem.style.boxShadow = '0 0 0 3px rgba(13,124,124,0.3)';

    document.getElementById('lblHoraSlotSeleccionado').innerText = hora;
    document.getElementById('slotSeleccionadoAlert').style.display = 'block';
}

function toggleNuevoResponsableFields() {
    const chk = document.getElementById('toggleNuevoResponsable');
    const container = document.getElementById('nuevoResponsableFields');
    if (chk && container) {
        container.style.display = chk.checked ? 'block' : 'none';
        ['nuevo_resp_ci', 'nuevo_resp_nombres', 'nuevo_resp_telefono', 'nuevo_resp_vigencia'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                if (chk.checked) el.setAttribute('required', 'required');
                else el.removeAttribute('required');
            }
        });
    }
}

function toggleTipoPaciente(tipo) {
    const seccionExistente = document.getElementById('seccionPacienteExistente');
    const seccionNuevo = document.getElementById('seccionPacienteNuevo');
    const crearNuevo = document.getElementById('crearPacienteNuevo');
    const selectPac = document.getElementById('selectPaciente');
    const inputBuscar = document.getElementById('inputBuscarPaciente');

    if (tipo === 'nuevo') {
        if (seccionExistente) seccionExistente.style.display = 'none';
        if (seccionNuevo) seccionNuevo.style.display = 'block';
        if (crearNuevo) crearNuevo.value = '1';
        if (selectPac) selectPac.removeAttribute('required');
        if (inputBuscar) inputBuscar.removeAttribute('required');

        ['nuevo_ci', 'nuevo_nombres', 'nuevo_apellidos', 'nuevo_fecha_nacimiento', 'nuevo_telefono', 'nuevo_email'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.setAttribute('required', 'required');
        });
        toggleNuevoResponsableFields();
        verificarFichaActiva(null); // Paciente nuevo no tiene ficha activa
    } else {
        if (seccionExistente) seccionExistente.style.display = 'block';
        if (seccionNuevo) seccionNuevo.style.display = 'none';
        if (crearNuevo) crearNuevo.value = '0';
        if (selectPac) selectPac.setAttribute('required', 'required');

        ['nuevo_ci', 'nuevo_nombres', 'nuevo_apellidos', 'nuevo_fecha_nacimiento', 'nuevo_telefono', 'nuevo_email', 'nuevo_resp_ci', 'nuevo_resp_nombres', 'nuevo_resp_telefono', 'nuevo_resp_vigencia'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.removeAttribute('required');
        });

        // Re-verificar paciente existente seleccionado
        const currentId = selectPac && selectPac.value ? parseInt(selectPac.value) : null;
        if (currentId) {
            verificarFichaActiva(pacientesCatalogo.find(p => p.id === currentId));
        } else {
            verificarFichaActiva(null);
        }
    }
}

const pacientesCatalogo = [
    @foreach($pacientes as $pac)
    @php
        $activa = $pac->citas->first();
        $fichaData = $activa ? [
            'codigo' => $activa->codigo_ficha,
            'fecha' => $activa->fecha ? $activa->fecha->format('d/m/Y') : '',
            'hora' => $activa->hora ? substr($activa->hora, 0, 5) : '',
            'medico' => $activa->medico?->user?->name ?? 'el especialista asignado',
            'especialidad' => $activa->medico?->especialidad?->nombre ?? '',
            'estado' => $activa->estado,
        ] : null;
    @endphp
    {
        id: {{ $pac->id }},
        nombre: @json($pac->nombre_completo),
        ci: @json($pac->ci),
        telefono: @json($pac->telefono ?? ''),
        label: @json($pac->nombre_completo . ' — CI: ' . $pac->ci . ($pac->telefono ? ' (Cel: ' . $pac->telefono . ')' : '')),
        tiene_ficha_activa: {{ $activa ? 'true' : 'false' }},
        ficha_info: @json($fichaData)
    },
    @endforeach
];

let pacienteBloqueadoPorFichaActiva = false;
let mensajeBloqueoFicha = '';

function verificarFichaActiva(p) {
    const alerta = document.getElementById('alertaFichaActiva');
    const alertaTexto = document.getElementById('alertaFichaActivaTexto');
    const btnSubmit = document.getElementById('btnConfirmarCita');

    if (p && p.tiene_ficha_activa && p.ficha_info) {
        pacienteBloqueadoPorFichaActiva = true;
        mensajeBloqueoFicha = `El paciente ${p.nombre} ya cuenta con una ficha médica activa (${p.ficha_info.codigo}) para el ${p.ficha_info.fecha} a las ${p.ficha_info.hora} con ${p.ficha_info.medico} (${p.ficha_info.especialidad}). No es posible emitir otra ficha mientras tenga una activa.`;

        if (alerta && alertaTexto) {
            alertaTexto.innerHTML = `El paciente <strong>${escapeHtml(p.nombre)}</strong> ya cuenta con la ficha médica activa <strong>${escapeHtml(p.ficha_info.codigo)}</strong> programada para el <strong>${escapeHtml(p.ficha_info.fecha)}</strong> a las <strong>${escapeHtml(p.ficha_info.hora)}</strong> con <strong>${escapeHtml(p.ficha_info.medico)}</strong> (${escapeHtml(p.ficha_info.especialidad)}).`;
            alerta.style.display = 'block';
        }

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.5';
            btnSubmit.style.cursor = 'not-allowed';
            btnSubmit.title = 'No es posible emitir la ficha: el paciente ya cuenta con una ficha médica activa.';
        }
    } else {
        pacienteBloqueadoPorFichaActiva = false;
        mensajeBloqueoFicha = '';
        if (alerta) alerta.style.display = 'none';
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.style.opacity = '1';
            btnSubmit.style.cursor = 'pointer';
            btnSubmit.title = '';
        }
    }
}

function cambioPacienteRolPaciente(id) {
    const p = pacientesCatalogo.find(item => item.id == id);
    verificarFichaActiva(p);
}

const inputBuscar = document.getElementById('inputBuscarPaciente');
const dropdown = document.getElementById('dropdownPacientes');
const selectPaciente = document.getElementById('selectPaciente');
const btnLimpiar = document.getElementById('btnLimpiarPaciente');
const infoBadge = document.getElementById('pacienteSeleccionadoInfo');
const lblVinculado = document.getElementById('lblPacienteVinculado');

function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function renderDropdown(items) {
    if (!dropdown) return;
    if (items.length === 0) {
        dropdown.innerHTML = '<div style="padding: 0.85rem; text-align: center; color: var(--color-text-muted); font-size: 0.85rem;">No se encontraron pacientes que coincidan.</div>';
    } else {
        dropdown.innerHTML = items.map(p => `
            <div class="paciente-dropdown-item" onclick="seleccionarPacienteItem(${p.id})" style="padding: 0.7rem 0.9rem; cursor: pointer; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; transition: background 0.15s ease;">
                <div>
                    <strong style="color: var(--color-primary); font-size: 0.9rem;">${escapeHtml(p.nombre)}</strong>
                    <span style="color: var(--color-text-muted); font-size: 0.8rem; margin-left: 0.4rem;">— CI: ${escapeHtml(p.ci)}</span>
                </div>
                <div style="display: flex; gap: 0.4rem; align-items: center;">
                    ${p.tiene_ficha_activa ? `<span style="font-size: 0.72rem; background: #fee2e2; color: #991b1b; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600; border: 1px solid #fecaca;">⛔ Ficha Activa: ${escapeHtml(p.ficha_info.codigo)}</span>` : ''}
                    ${p.telefono ? `<span style="font-size: 0.75rem; background: var(--color-surface-hover); padding: 0.2rem 0.5rem; border-radius: 4px; color: var(--color-text-muted);">Cel: ${escapeHtml(p.telefono)}</span>` : ''}
                </div>
            </div>
        `).join('');

        dropdown.querySelectorAll('.paciente-dropdown-item').forEach(el => {
            el.addEventListener('mouseenter', () => el.style.background = 'rgba(13,124,124,0.06)');
            el.addEventListener('mouseleave', () => el.style.background = 'transparent');
        });
    }
    dropdown.style.display = 'block';
}

function filtrarPacientes(query) {
    const term = (query || '').toLowerCase().trim();
    if (!term) {
        return pacientesCatalogo.slice(0, 30);
    }
    return pacientesCatalogo.filter(p => 
        p.nombre.toLowerCase().includes(term) ||
        p.ci.toLowerCase().includes(term) ||
        p.telefono.toLowerCase().includes(term)
    );
}

if (inputBuscar) {
    inputBuscar.addEventListener('focus', () => {
        const query = (selectPaciente && selectPaciente.value) ? '' : inputBuscar.value;
        renderDropdown(filtrarPacientes(query));
    });

    inputBuscar.addEventListener('input', () => {
        if (selectPaciente) selectPaciente.value = '';
        if (infoBadge) infoBadge.style.display = 'none';
        if (btnLimpiar) btnLimpiar.style.display = inputBuscar.value ? 'block' : 'none';
        verificarFichaActiva(null);
        renderDropdown(filtrarPacientes(inputBuscar.value));
    });

    document.addEventListener('click', (e) => {
        if (!inputBuscar.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function seleccionarPacienteItem(id) {
    const p = pacientesCatalogo.find(item => item.id === id);
    if (!p) return;
    if (selectPaciente) selectPaciente.value = p.id;
    if (inputBuscar) inputBuscar.value = p.label;
    if (dropdown) dropdown.style.display = 'none';
    if (btnLimpiar) btnLimpiar.style.display = 'block';
    if (infoBadge) {
        infoBadge.style.display = 'flex';
        if (lblVinculado) {
            lblVinculado.innerHTML = p.tiene_ficha_activa 
                ? `${escapeHtml(p.nombre)} (CI: ${escapeHtml(p.ci)}) <span style="color: #991b1b; font-weight: 700; margin-left: 0.5rem;">[⛔ Ficha Activa: ${escapeHtml(p.ficha_info.codigo)}]</span>`
                : `${escapeHtml(p.nombre)} (CI: ${escapeHtml(p.ci)})`;
        }
    }
    verificarFichaActiva(p);
}

function limpiarSeleccionPaciente() {
    if (selectPaciente) selectPaciente.value = '';
    if (inputBuscar) {
        inputBuscar.value = '';
        inputBuscar.focus();
    }
    if (btnLimpiar) btnLimpiar.style.display = 'none';
    if (infoBadge) infoBadge.style.display = 'none';
    verificarFichaActiva(null);
    renderDropdown(pacientesCatalogo.slice(0, 30));
}

const wizardFormElem = document.getElementById('wizardForm');
if (wizardFormElem) {
    wizardFormElem.addEventListener('submit', function(e) {
        if (pacienteBloqueadoPorFichaActiva) {
            alert(mensajeBloqueoFicha || 'No es posible emitir otra ficha porque el paciente ya tiene una ficha activa.');
            e.preventDefault();
            return false;
        }

        const crearNuevo = document.getElementById('crearPacienteNuevo');
        if (crearNuevo && crearNuevo.value === '0') {
            const selectPac = document.getElementById('selectPaciente');
            if (selectPac && !selectPac.value) {
                alert('Por favor selecciona o busca un paciente de la lista en el campo de paciente.');
                if (inputBuscar) inputBuscar.focus();
                e.preventDefault();
                return false;
            }
        }
    });
}

// Inicialización de ficha activa si hay paciente preseleccionado
document.addEventListener('DOMContentLoaded', function() {
    const preselectedId = {{ $selectedPacienteId ? (int)$selectedPacienteId : 'null' }};
    if (preselectedId) {
        const p = pacientesCatalogo.find(item => item.id === preselectedId);
        if (p) {
            verificarFichaActiva(p);
        }
    }
});

function actualizarResumenFinal() {
    document.getElementById('lblConfEsp').innerText = selectedEspName;
    document.getElementById('lblConfMed').innerText = selectedMedName;
    document.getElementById('lblConfFechaHora').innerText = `${document.getElementById('inputFecha').value} a las ${selectedSlotHora} hrs`;
}
</script>
@endsection
