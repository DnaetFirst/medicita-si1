@extends('layouts.app')

@section('content')
<div style="max-width: 820px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin: 0;">
                Reprogramar Cita Médica (CU11)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin: 0.25rem 0 0 0;">
                Ficha actual: <strong>{{ $cita->codigo_ficha }}</strong> &bull; Paciente: <strong>{{ $cita->paciente?->nombre_completo }}</strong>
            </p>
        </div>
        <a href="{{ route('citas.show', $cita) }}" class="btn btn-secondary">
            &larr; Volver
        </a>
    </div>

    <!-- Ficha actual -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem; background: var(--color-surface-hover); border-left: 4px solid var(--color-secondary);">
        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; color: var(--color-primary);">Turno Programado Actual</h4>
        <div class="grid-3" style="font-size: 0.85rem;">
            <div>Médico: <strong>{{ $cita->medico?->user?->name }}</strong></div>
            <div>Especialidad: <strong>{{ $cita->medico?->especialidad?->nombre }}</strong></div>
            <div>Fecha y Hora: <strong style="color: var(--color-danger);">{{ $cita->fecha?->format('d/m/Y') }} a las {{ substr($cita->hora, 0, 5) }} hrs</strong></div>
        </div>
        <small style="color: var(--color-text-muted); display: block; margin-top: 0.5rem;">
            * Al confirmar la reprogramación, este cupo se liberará automáticamente y pasará a estar disponible para otros pacientes.
        </small>
    </div>

    <!-- Formulario de Reprogramación -->
    <form method="POST" action="{{ route('citas.reprogramar.post', $cita) }}" class="card" style="padding: 2rem;">
        @csrf
        <input type="hidden" name="nuevo_slot_id" id="hiddenNuevoSlotId" required>

        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
            1. Selecciona la Nueva Fecha y Turno
        </h3>

        <div style="margin-bottom: 1.25rem;">
            <label class="form-label">Nueva Fecha de Atención *</label>
            <input type="date" id="inputNuevaFecha" class="form-input" value="{{ today()->addDay()->toDateString() }}" min="{{ date('Y-m-d') }}" onchange="cargarSlotsNuevos()" style="max-width: 300px; font-weight: 600;">
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label class="form-label">Turnos Disponibles para la Nueva Fecha (15 min) *</label>
            
            <div id="slotsReprogramarLoading" style="display: none; text-align: center; padding: 1.5rem; color: var(--color-primary);">
                Cargando turnos de agenda médica...
            </div>

            <div id="slotsReprogramarContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.6rem; max-height: 280px; overflow-y: auto; padding: 0.5rem 0.25rem;">
                <!-- Rellenado con JS -->
            </div>

            <div id="alertSlotElegido" style="display: none; margin-top: 0.75rem; background: #ecfdf5; border-left: 4px solid #10b981; padding: 0.75rem; border-radius: var(--radius-md); font-size: 0.85rem; color: #065f46;">
                Nuevo horario seleccionado: <strong id="lblNuevoHorario"></strong>
            </div>
        </div>

        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
            2. Justificación de Reprogramación (CU11)
        </h3>

        <div style="margin-bottom: 1.5rem;">
            <label class="form-label">Motivo de Reprogramación *</label>
            <textarea name="motivo_reprogramacion" class="form-input" rows="3" required placeholder="Ej. Solicitud del paciente por cambio de horario laboral, reprogramación médica, etc."></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
            <a href="{{ route('citas.show', $cita) }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 2rem;">
                Confirmar Reprogramación
            </button>
        </div>
    </form>
</div>

<script>
const medicoId = {{ $cita->medico_id }};

async function cargarSlotsNuevos() {
    const fecha = document.getElementById('inputNuevaFecha').value;
    const container = document.getElementById('slotsReprogramarContainer');
    const loading = document.getElementById('slotsReprogramarLoading');

    container.innerHTML = '';
    loading.style.display = 'block';

    try {
        const response = await fetch(`/api/agenda/slots?medico_id=${medicoId}&fecha=${fecha}`);
        const data = await response.json();
        loading.style.display = 'none';

        if (!data.slots || data.slots.length === 0) {
            container.innerHTML = '<div style="grid-column: 1 / -1; color: var(--color-text-muted); padding: 1.5rem; text-align: center;">No hay turnos disponibles para esta fecha. Selecciona otro día.</div>';
            return;
        }

        data.slots.forEach(slot => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const hora = slot.hora_inicio.substr(0, 5);
            btn.innerText = hora;
            btn.style.padding = '0.6rem 0.4rem';
            btn.style.borderRadius = 'var(--radius-md)';
            btn.style.fontSize = '0.85rem';
            btn.style.fontWeight = '700';
            btn.style.cursor = 'pointer';
            btn.style.border = '1px solid var(--color-border)';

            if (slot.estado === 'disponible') {
                btn.style.background = '#ecfdf5';
                btn.style.color = '#065f46';
                btn.style.borderColor = '#10b981';
                btn.onclick = () => {
                    document.getElementById('hiddenNuevoSlotId').value = slot.id;
                    document.querySelectorAll('#slotsReprogramarContainer button').forEach(b => {
                        b.style.background = '#ecfdf5';
                        b.style.color = '#065f46';
                        b.style.boxShadow = 'none';
                    });
                    btn.style.background = 'var(--color-primary)';
                    btn.style.color = 'white';
                    btn.style.boxShadow = '0 0 0 3px rgba(13,124,124,0.3)';
                    document.getElementById('lblNuevoHorario').innerText = `${fecha} a las ${hora} hrs`;
                    document.getElementById('alertSlotElegido').style.display = 'block';
                };
            } else if (slot.estado === 'descanso') {
                btn.style.background = '#fef3c7';
                btn.style.color = '#92400e';
                btn.style.cursor = 'not-allowed';
                btn.disabled = true;
                btn.innerHTML = `${hora}<br><span style="font-size:0.6rem;">Descanso</span>`;
            } else {
                btn.style.background = '#fee2e2';
                btn.style.color = '#991b1b';
                btn.style.cursor = 'not-allowed';
                btn.disabled = true;
            }

            container.appendChild(btn);
        });

    } catch (e) {
        loading.style.display = 'none';
        container.innerHTML = '<div style="grid-column: 1 / -1; color: var(--color-danger); text-align: center;">Error al consultar turnos.</div>';
    }
}

document.addEventListener('DOMContentLoaded', cargarSlotsNuevos);
</script>
@endsection
