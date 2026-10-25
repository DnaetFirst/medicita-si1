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
        <a href="{{ route('citas.index') }}" class="btn btn-secondary">
            &larr; Volver al Tablero
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
            <div id="seccionPacienteExistente" style="margin-bottom: 1.5rem;">
                <label class="form-label">Selecciona el Paciente *</label>
                <select name="paciente_id" id="selectPaciente" class="form-input" style="width: 100%; font-size: 0.95rem;">
                    <option value="">Selecciona paciente por nombre o C.I...</option>
                    @foreach($pacientes as $pac)
                        <option value="{{ $pac->id }}" {{ ($selectedPacienteId == $pac->id) ? 'selected' : '' }}>
                            {{ $pac->nombre_completo }} — CI: {{ $pac->ci }} (Cel: {{ $pac->telefono }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Nuevo Paciente Inline -->
            <input type="hidden" name="crear_paciente_nuevo" id="crearPacienteNuevo" value="0">
            <div id="seccionPacienteNuevo" style="display: none; background: var(--color-surface-hover); padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px dashed var(--color-border);">
                <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; color: var(--color-primary); font-weight: 700;">Datos del Nuevo Paciente</h4>
                <div class="grid-2" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label">Cédula de Identidad (CI) *</label>
                        <input type="text" name="nuevo_ci" class="form-input" placeholder="Ej. 9988776">
                    </div>
                    <div>
                        <label class="form-label">Fecha de Nacimiento *</label>
                        <input type="date" name="nuevo_fecha_nacimiento" class="form-input" max="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom: 0.75rem;">
                    <div>
                        <label class="form-label">Nombres *</label>
                        <input type="text" name="nuevo_nombres" class="form-input" placeholder="Ej. Ana Lucía">
                    </div>
                    <div>
                        <label class="form-label">Apellidos *</label>
                        <input type="text" name="nuevo_apellidos" class="form-input" placeholder="Ej. Paz Terrazas">
                    </div>
                </div>
                <div>
                    <label class="form-label">Teléfono de Contacto *</label>
                    <input type="text" name="nuevo_telefono" class="form-input" placeholder="Ej. 78012345">
                </div>
            </div>

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
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; font-size: 1rem; box-shadow: 0 4px 14px rgba(13, 124, 124, 0.25);">
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
    selectedSlotId = null;
    avanzarPaso(2);
}

// Cargar médicos de la especialidad vía AJAX o datos
const medicosPorEspecialidad = {
    @foreach($especialidades as $esp)
        "{{ $esp->id }}": [
            @foreach($esp->medicos as $m)
                { id: {{ $m->id }}, nombre: "{{ addslashes($m->user?->name) }}", registro: "{{ $m->nro_registro_medico }}" },
            @endforeach
        ],
    @endforeach
};

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
                    <div style="font-weight: 700; color: var(--color-primary);">${m.nombre}</div>
                    <div style="font-size: 0.75rem; color: var(--color-text-muted);">Matrícula: ${m.registro}</div>
                </div>
                <span class="badge badge-success">Activo</span>
            </div>
        `;
        container.appendChild(div);
    });
}

function seleccionarMedico(id, nombre) {
    selectedMedId = id;
    selectedMedName = nombre;
    document.getElementById('hiddenMedicoId').value = id;
    cargarMedicosPorEspecialidad(); // Refrescar border activo
}

function cambioFecha() {
    const fecha = document.getElementById('inputFecha').value;
    document.getElementById('hiddenFecha').value = fecha;
    if (currentStep === 3) {
        cargarSlotsAgenda();
    }
}

async function cargarSlotsAgenda() {
    const fecha = document.getElementById('inputFecha').value;
    document.getElementById('lblResumenMedico').innerText = selectedMedName;
    document.getElementById('lblResumenFecha').innerText = fecha;

    const container = document.getElementById('slotsContainer');
    const loading = document.getElementById('slotsLoading');

    container.innerHTML = '';
    loading.style.display = 'block';

    try {
        const response = await fetch(`/api/agenda/slots?medico_id=${selectedMedId}&fecha=${fecha}`);
        const data = await response.json();
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

            if (slot.estado === 'disponible') {
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

function toggleTipoPaciente(tipo) {
    if (tipo === 'nuevo') {
        document.getElementById('seccionPacienteExistente').style.display = 'none';
        document.getElementById('seccionPacienteNuevo').style.display = 'block';
        document.getElementById('crearPacienteNuevo').value = '1';
        document.getElementById('selectPaciente').removeAttribute('required');
    } else {
        document.getElementById('seccionPacienteExistente').style.display = 'block';
        document.getElementById('seccionPacienteNuevo').style.display = 'none';
        document.getElementById('crearPacienteNuevo').value = '0';
        document.getElementById('selectPaciente').setAttribute('required', 'required');
    }
}

function actualizarResumenFinal() {
    document.getElementById('lblConfEsp').innerText = selectedEspName;
    document.getElementById('lblConfMed').innerText = selectedMedName;
    document.getElementById('lblConfFechaHora').innerText = `${document.getElementById('inputFecha').value} a las ${selectedSlotHora} hrs`;
}
</script>
@endsection
