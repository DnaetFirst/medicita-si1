@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
                Reportes Analíticos e Indicadores Clínicos (CU17)
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.95rem;">
                Tablero de métricas operacionales, ocupación de turnos, cancelaciones y asistente inteligente de consultas.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="{{ route('reportes.csv', request()->query()) }}" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.4rem;" title="Descargar reporte actual como CSV (Excel)">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descargar CSV
            </a>
            <a href="{{ route('reportes.pdf.binario', request()->query()) }}" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem;" title="Descargar reporte actual como PDF">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Descargar PDF
            </a>
        </div>
    </div>

    <!-- TARJETAS DE INDICADORES / KPIS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="padding: 1rem; border-left: 4px solid var(--color-primary);">
            <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Total Citas</div>
            <div style="font-size: 1.85rem; font-weight: 700; color: var(--color-primary); margin: 0.25rem 0;">{{ $kpis['total_citas'] }}</div>
            <div style="font-size: 0.75rem; color: var(--color-text-muted);">En período seleccionado</div>
        </div>

        <div class="card" style="padding: 1rem; border-left: 4px solid #10b981;">
            <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Atendidas</div>
            <div style="font-size: 1.85rem; font-weight: 700; color: #10b981; margin: 0.25rem 0;">{{ $kpis['completadas'] }}</div>
            <div style="font-size: 0.75rem; color: #047857; font-weight: 600;">{{ $kpis['tasa_asistencia'] }}% asistencia</div>
        </div>

        <div class="card" style="padding: 1rem; border-left: 4px solid #ef4444;">
            <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Canceladas</div>
            <div style="font-size: 1.85rem; font-weight: 700; color: #ef4444; margin: 0.25rem 0;">{{ $kpis['canceladas'] }}</div>
            <div style="font-size: 0.75rem; color: #b91c1c; font-weight: 600;">{{ $kpis['tasa_cancelacion'] }}% del total</div>
        </div>

        <div class="card" style="padding: 1rem; border-left: 4px solid #f59e0b;">
            <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">No-Shows (Inasistencias)</div>
            <div style="font-size: 1.85rem; font-weight: 700; color: #f59e0b; margin: 0.25rem 0;">{{ $kpis['inasistencias'] }}</div>
            <div style="font-size: 0.75rem; color: #b45309; font-weight: 600;">{{ $kpis['tasa_no_show'] }}% tasa de falta</div>
        </div>

        <div class="card" style="padding: 1rem; border-left: 4px solid #06b6d4;">
            <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Cupos Recuperados</div>
            <div style="font-size: 1.85rem; font-weight: 700; color: #06b6d4; margin: 0.25rem 0;">{{ $kpis['cupos_recuperados'] }}</div>
            <div style="font-size: 0.75rem; color: #0e7490; font-weight: 600;">{{ $kpis['tasa_recuperacion'] }}% sobre canceladas</div>
        </div>

        <div class="card" style="padding: 1rem; border-left: 4px solid #8b5cf6;">
            <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase;">Ocupación de Turnos</div>
            <div style="font-size: 1.85rem; font-weight: 700; color: #8b5cf6; margin: 0.25rem 0;">{{ $kpis['tasa_ocupacion'] }}%</div>
            <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ $kpis['slots_reservados'] }} de {{ $kpis['total_slots'] }} slots</div>
        </div>
    </div>

    <!-- FILTROS TRADICIONALES -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 0.75rem; color: var(--color-primary);">
            Filtros del Reporte
        </h2>
        <form method="GET" action="{{ route('reportes.index') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; align-items: flex-end;">
            <div>
                <label for="fecha_inicio" style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 0.25rem;">Fecha Desde</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" value="{{ $filtros['fecha_inicio'] ?? '' }}" class="form-input" style="width: 100%; padding: 0.4rem; border: 1px solid var(--color-border); border-radius: 4px;">
            </div>

            <div>
                <label for="fecha_fin" style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 0.25rem;">Fecha Hasta</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="{{ $filtros['fecha_fin'] ?? '' }}" class="form-input" style="width: 100%; padding: 0.4rem; border: 1px solid var(--color-border); border-radius: 4px;">
            </div>

            <div>
                <label for="especialidad_id" style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 0.25rem;">Especialidad</label>
                <select id="especialidad_id" name="especialidad_id" class="form-input" style="width: 100%; padding: 0.4rem; border: 1px solid var(--color-border); border-radius: 4px;">
                    <option value="">Todas las especialidades</option>
                    @foreach($especialidades as $esp)
                        <option value="{{ $esp->id }}" {{ ($filtros['especialidad_id'] ?? '') == $esp->id ? 'selected' : '' }}>
                            {{ $esp->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="medico_id" style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 0.25rem;">Médico</label>
                <select id="medico_id" name="medico_id" class="form-input" style="width: 100%; padding: 0.4rem; border: 1px solid var(--color-border); border-radius: 4px;">
                    <option value="">Todos los médicos</option>
                    @foreach($medicos as $med)
                        <option value="{{ $med->id }}" {{ ($filtros['medico_id'] ?? '') == $med->id ? 'selected' : '' }}>
                            Dr(a). {{ $med->user->name }} ({{ $med->especialidad?->nombre ?? '' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="estado" style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 0.25rem;">Estado de Cita</label>
                <select id="estado" name="estado" class="form-input" style="width: 100%; padding: 0.4rem; border: 1px solid var(--color-border); border-radius: 4px;">
                    <option value="">Todos los estados</option>
                    <option value="programada" {{ ($filtros['estado'] ?? '') == 'programada' ? 'selected' : '' }}>Programada</option>
                    <option value="confirmada" {{ ($filtros['estado'] ?? '') == 'confirmada' ? 'selected' : '' }}>Confirmada</option>
                    <option value="en_espera" {{ ($filtros['estado'] ?? '') == 'en_espera' ? 'selected' : '' }}>En Espera</option>
                    <option value="en_atencion" {{ ($filtros['estado'] ?? '') == 'en_atencion' ? 'selected' : '' }}>En Atención</option>
                    <option value="atendida" {{ ($filtros['estado'] ?? '') == 'atendida' ? 'selected' : '' }}>Atendida</option>
                    <option value="cancelada" {{ ($filtros['estado'] ?? '') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                    <option value="reprogramada" {{ ($filtros['estado'] ?? '') == 'reprogramada' ? 'selected' : '' }}>Reprogramada</option>
                    <option value="inasistencia" {{ ($filtros['estado'] ?? '') == 'inasistencia' ? 'selected' : '' }}>Inasistencia (No-Show)</option>
                    <option value="postergada" {{ ($filtros['estado'] ?? '') == 'postergada' ? 'selected' : '' }}>Postergada (Bloqueo)</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.45rem 0.75rem;">Filtrar</button>
                <a href="{{ route('reportes.index') }}" class="btn btn-secondary" style="padding: 0.45rem 0.75rem;" title="Limpiar Filtros">↺</a>
            </div>
        </form>
    </div>

    <!-- ASISTENTE CONVERSACIONAL DE IA (CU17) -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #bfdbfe; background: linear-gradient(180deg, #f8fafc 0%, #eff6ff 100%); border-radius: 8px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: #2563eb; color: white;">
                    ✨
                </span>
                <div>
                    <h2 style="font-size: 1.15rem; font-weight: 700; color: #1e3a8a; margin: 0;">
                        Asistente Analítico Inteligente (IA)
                    </h2>
                    <p style="font-size: 0.8rem; color: #475569; margin: 0;">
                        Formula preguntas en lenguaje natural o mediante dictado de voz. Ejecuta consultas analíticas con validador de seguridad AST sobre vistas de solo lectura.
                    </p>
                </div>
            </div>
            <span style="font-size: 0.75rem; background: #dbeafe; color: #1e40af; padding: 0.25rem 0.5rem; border-radius: 9999px; font-weight: 600;">
                Seguridad AST Activa · Cero Acceso Clínico
            </span>
        </div>

        <!-- Preguntas rápidas de sugerencia -->
        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem;">
            <span style="font-size: 0.75rem; color: #64748b; align-self: center;">Sugerencias:</span>
            <button type="button" class="sugerencia-btn" onclick="colocarPregunta('¿Cuántas citas se cancelaron este mes y por qué motivo?')">
                Cancelaciones por motivo
            </button>
            <button type="button" class="sugerencia-btn" onclick="colocarPregunta('¿Cuál es la tasa de inasistencia (no-shows) y qué especialidades tienen más faltas?')">
                Inasistencias por especialidad
            </button>
            <button type="button" class="sugerencia-btn" onclick="colocarPregunta('¿Cuántos cupos fueron recuperados exitosamente mediante la lista de espera?')">
                Cupos recuperados (Lista de Espera)
            </button>
            <button type="button" class="sugerencia-btn" onclick="colocarPregunta('¿Cuál es la ocupación de turnos y disponibilidad de slots por fecha?')">
                Ocupación de turnos
            </button>
            <button type="button" class="sugerencia-btn" onclick="colocarPregunta('¿Cuáles son los médicos con mayor cantidad de citas atendidas?')">
                Médicos con mayor atención
            </button>
            <button type="button" class="sugerencia-btn" onclick="colocarPregunta('¿Cuál es la demanda de citas por especialidad médica?')">
                Demanda por especialidad
            </button>
        </div>

        <!-- Caja de entrada con micrófono -->
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: stretch; margin-bottom: 0.4rem;">
            <textarea id="ia-pregunta" rows="2" maxlength="1000" aria-label="Pregunta para el asistente de reportes" placeholder="Escribe una pregunta o pulsa el micrófono para grabarla..." style="flex: 1 1 260px; min-width: 0; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; resize: vertical; font-family: inherit;"></textarea>

            <button type="button" id="btn-microfono" class="btn" style="background: #e2e8f0; color: #334155; display: inline-flex; align-items: center; justify-content: center; width: 44px; padding: 0; user-select: none; -webkit-user-select: none; transition: all 0.2s ease;" title="Grabar pregunta" aria-label="Grabar pregunta" aria-pressed="false">
                <svg id="mic-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
            </button>

            <button type="button" id="btn-consultar-ia" class="btn btn-primary" style="padding: 0.6rem 1.25rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span id="ia-btn-texto">Consultar</span>
                <span id="ia-spinner" style="display: none;">⏳</span>
            </button>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; font-size: 0.75rem; color: #64748b; flex-wrap: wrap; gap: 0.25rem;">
            <span>🎙️ <strong>Dictado por Voz:</strong> Pulsa para grabar y vuelve a pulsar para terminar (máximo 60 segundos). El audio se envía a Gemini para transcribirlo; revisa el texto antes de consultar.</span>
            <span id="voz-estado" role="status" aria-live="polite" style="display: none; color: #dc2626; font-weight: 600; ">Grabando... Pulsa para terminar</span>
        </div>

        <!-- Contenedor dinámico de respuesta de IA -->
        <div id="ia-resultado-contenedor" style="display: none; margin-top: 1rem; border-top: 1px solid #cbd5e1; padding-top: 1rem;">
            <!-- Banner de título del reporte IA -->
            <div id="ia-titulo-reporte" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.82rem; font-weight: 600; color: #1e40af; margin-bottom: 0.75rem;"></div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span id="ia-estado-badge" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;"></span>
                    <span id="ia-metadatos" style="font-size: 0.8rem; color: #64748b;"></span>
                </div>
                <div id="ia-grafico-sugerido" style="font-size: 0.75rem; color: #475569; font-weight: 500;"></div>
            </div>

            <div id="ia-mensaje-error" style="display: none; padding: 0.75rem; border-radius: 6px; background: #fee2e2; color: #991b1b; font-size: 0.85rem; margin-bottom: 0.75rem;"></div>

            <div id="ia-tabla-scroll" style="max-height: 350px; overflow-y: auto; overflow-x: auto; background: white; border: 1px solid #e2e8f0; border-radius: 6px;">
                <table id="ia-tabla" class="table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead id="ia-tabla-head" style="background: #f1f5f9; position: sticky; top: 0;"></thead>
                    <tbody id="ia-tabla-body"></tbody>
                </table>
            </div>

            <div id="ia-exportar" class="cycle-actions" style="display:none; margin-top:1rem;"></div>
            <!-- Detalle transparente del SQL generado -->
            <details style="margin-top: 0.75rem; font-size: 0.8rem; color: #475569;">
                <summary style="cursor: pointer; font-weight: 600; color: #2563eb;">Transparencia: Ver consulta SQL validada</summary>
                <pre id="ia-sql-debug" style="margin-top: 0.4rem; padding: 0.6rem; background: #0f172a; color: #38bdf8; border-radius: 4px; overflow-x: auto; font-size: 0.75rem; white-space: pre-wrap;"></pre>
            </details>
        </div>
    </div>

    <!-- CONSTRUCTOR VISUAL DE REPORTES (CU17 TIPO 2) -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #d1fae5; background: linear-gradient(180deg, #f8fafc 0%, #f0fdf4 100%); border-radius: 8px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: #059669; color: white; font-size: 1rem;">📊</span>
                <div>
                    <h2 style="font-size: 1.1rem; font-weight: 700; color: #064e3b; margin: 0;">Constructor Visual de Reportes (Tipo 2)</h2>
                    <p style="font-size: 0.8rem; color: #475569; margin: 0;">Selecciona columnas, agrupación y métricas. Previsualiza el resultado y descarga en PDF o Excel.</p>
                </div>
            </div>
            <span style="font-size: 0.75rem; background: #d1fae5; color: #065f46; padding: 0.25rem 0.5rem; border-radius: 9999px; font-weight: 600;">Personalizable</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1rem;" class="constructor-grid">
            {{-- Columnas disponibles --}}
            <div>
                <div style="font-size: 0.85rem; font-weight: 600; color: #064e3b; margin-bottom: 0.5rem;">📋 Columnas a incluir</div>
                <div style="border: 1px solid #a7f3d0; border-radius: 6px; padding: 0.75rem; background: white; max-height: 220px; overflow-y: auto;">
                    @foreach($columnasDisponibles as $clave => $etiqueta)
                    <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; color: #1f2937; padding: 0.2rem 0; cursor: pointer;">
                        <input type="checkbox" name="col_constructor" value="{{ $clave }}"
                            {{ in_array($clave, ['fecha', 'especialidad', 'medico', 'estado']) ? 'checked' : '' }}
                            style="accent-color: #059669;">
                        {{ $etiqueta }}
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Agrupación --}}
            <div>
                <div style="font-size: 0.85rem; font-weight: 600; color: #064e3b; margin-bottom: 0.5rem;">🗂️ Agrupar por</div>
                <select id="constructor-agrupacion" style="width: 100%; padding: 0.5rem; border: 1px solid #a7f3d0; border-radius: 6px; font-size: 0.85rem; margin-bottom: 0.75rem; background: white;">
                    @foreach($agrupacionesDisponibles as $clave => $etiqueta)
                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>

                <div style="font-size: 0.85rem; font-weight: 600; color: #064e3b; margin-bottom: 0.5rem;">📈 Métricas (con agrupación)</div>
                <div id="constructor-metricas-box" style="border: 1px solid #a7f3d0; border-radius: 6px; padding: 0.75rem; background: white;">
                    @foreach($metricasDisponibles as $clave => $etiqueta)
                    <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; color: #1f2937; padding: 0.2rem 0; cursor: pointer;">
                        <input type="checkbox" name="met_constructor" value="{{ $clave }}"
                            {{ $clave === 'total' ? 'checked' : '' }}
                            style="accent-color: #059669;">
                        {{ $etiqueta }}
                    </label>
                    @endforeach
                </div>

                {{-- Filtros de fecha rápidos para el constructor --}}
                <div style="margin-top: 0.75rem;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: #064e3b; margin-bottom: 0.4rem;">📅 Período (opcional)</div>
                    <div style="display: flex; gap: 0.4rem;">
                        <input type="date" id="constructor-fecha-inicio" placeholder="Desde"
                            value="{{ $filtros['fecha_inicio'] ?? '' }}"
                            style="flex: 1; padding: 0.35rem; border: 1px solid #a7f3d0; border-radius: 4px; font-size: 0.8rem;">
                        <input type="date" id="constructor-fecha-fin" placeholder="Hasta"
                            value="{{ $filtros['fecha_fin'] ?? '' }}"
                            style="flex: 1; padding: 0.35rem; border: 1px solid #a7f3d0; border-radius: 4px; font-size: 0.8rem;">
                    </div>
                </div>
            </div>

            {{-- Preview y exportación --}}
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <div style="font-size: 0.85rem; font-weight: 600; color: #064e3b; margin-bottom: 0.25rem;">🔍 Vista previa y descarga</div>
                <button type="button" id="btn-constructor-preview" class="btn btn-primary" style="background: #059669; border-color: #059669; display: flex; align-items: center; gap: 0.4rem; justify-content: center;">
                    <span id="constructor-spinner" style="display: none;">⏳</span>
                    <span>Vista Previa del Reporte</span>
                </button>

                {{-- Nombre del reporte generado --}}
                <div id="constructor-titulo-box" style="display: none; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 0.5rem; font-size: 0.8rem;">
                    <div style="font-weight: 600; color: #065f46; margin-bottom: 0.2rem;">📄 Reporte:</div>
                    <div id="constructor-titulo-texto" style="color: #1f2937; word-break: break-word;"></div>
                    <div style="font-size: 0.7rem; color: #6b7280; margin-top: 0.2rem;" id="constructor-total-texto"></div>
                </div>

                {{-- Botones de descarga --}}
                <div id="constructor-descargas" style="display: none; display: flex; flex-direction: column; gap: 0.4rem;">
                    <form id="form-constructor-csv" method="POST" action="{{ route('reportes.constructor.csv') }}" style="margin: 0;">
                        @csrf
                        <input type="hidden" name="_columnas_csv" value="">
                        <input type="hidden" name="_agrupacion_csv" value="">
                        <input type="hidden" name="_metricas_csv" value="">
                        <input type="hidden" name="_fecha_inicio_csv" value="">
                        <input type="hidden" name="_fecha_fin_csv" value="">
                        <button type="submit" class="btn btn-secondary" style="width: 100%; display: flex; align-items: center; gap: 0.4rem; justify-content: center; font-size: 0.82rem;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Descargar CSV / Excel
                        </button>
                    </form>
                    <form id="form-constructor-pdf" method="POST" action="{{ route('reportes.constructor.pdf') }}" style="margin: 0;">
                        @csrf
                        <input type="hidden" name="_columnas_pdf" value="">
                        <input type="hidden" name="_agrupacion_pdf" value="">
                        <input type="hidden" name="_metricas_pdf" value="">
                        <input type="hidden" name="_fecha_inicio_pdf" value="">
                        <input type="hidden" name="_fecha_fin_pdf" value="">
                        <button type="submit" class="btn btn-primary" style="width: 100%; display: flex; align-items: center; gap: 0.4rem; justify-content: center; font-size: 0.82rem; background: #059669; border-color: #059669;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Descargar PDF
                        </button>
                    </form>
                </div>

                {{-- Estado --}}
                <div id="constructor-estado" style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;"></div>
            </div>
        </div>

        {{-- Tabla de vista previa del constructor --}}
        <div id="constructor-preview-box" style="display: none; margin-top: 1rem; border-top: 1px solid #a7f3d0; padding-top: 1rem;">
            <div style="font-size: 0.85rem; font-weight: 600; color: #064e3b; margin-bottom: 0.5rem;">Vista Previa (primeros 50 registros)</div>
            <div style="overflow-x: auto; max-height: 320px; overflow-y: auto; border: 1px solid #a7f3d0; border-radius: 6px; background: white;">
                <table id="constructor-tabla" class="table" style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
                    <thead id="constructor-tabla-head" style="background: #059669; color: white; position: sticky; top: 0;"></thead>
                    <tbody id="constructor-tabla-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- LISTADO DETALLADO DE CITAS -->
    <div class="card" style="padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <h2 style="font-size: 1.05rem; font-weight: 600; color: var(--color-primary); margin: 0;">
                Detalle Operacional de Citas ({{ $citas->total() }} registros)
            </h2>
            <div style="font-size: 0.8rem; color: var(--color-text-muted);">
                Página {{ $citas->currentPage() }} de {{ $citas->lastPage() }}
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead style="background: var(--color-bg-light); text-align: left;">
                    <tr>
                        <th style="padding: 0.6rem 0.75rem; border-bottom: 2px solid var(--color-border);">Ficha</th>
                        <th style="padding: 0.6rem 0.75rem; border-bottom: 2px solid var(--color-border);">Fecha / Hora</th>
                        <th style="padding: 0.6rem 0.75rem; border-bottom: 2px solid var(--color-border);">Especialidad</th>
                        <th style="padding: 0.6rem 0.75rem; border-bottom: 2px solid var(--color-border);">Médico</th>
                        <th style="padding: 0.6rem 0.75rem; border-bottom: 2px solid var(--color-border);">Paciente</th>
                        <th style="padding: 0.6rem 0.75rem; border-bottom: 2px solid var(--color-border);">Estado</th>
                        <th style="padding: 0.6rem 0.75rem; border-bottom: 2px solid var(--color-border);">Recuperado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($citas as $c)
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 0.6rem 0.75rem; font-family: monospace; font-weight: 600;">
                                {{ $c->codigo_ficha }}
                            </td>
                            <td style="padding: 0.6rem 0.75rem;">
                                {{ $c->fecha ? $c->fecha->format('d/m/Y') : '' }}
                                <span style="color: var(--color-text-muted); font-size: 0.75rem;">({{ substr($c->hora, 0, 5) }})</span>
                            </td>
                            <td style="padding: 0.6rem 0.75rem;">
                                {{ $c->medico?->especialidad?->nombre ?? 'N/A' }}
                            </td>
                            <td style="padding: 0.6rem 0.75rem;">
                                Dr(a). {{ $c->medico?->user?->name ?? 'N/A' }}
                            </td>
                            <td style="padding: 0.6rem 0.75rem;">
                                {{ $c->paciente ? trim($c->paciente->nombres.' '.$c->paciente->apellidos) : 'N/A' }}
                                <div style="font-size: 0.7rem; color: var(--color-text-muted);">CI: {{ $c->paciente->ci ?? '' }}</div>
                            </td>
                            <td style="padding: 0.6rem 0.75rem;">
                                @php
                                    $estadoColor = match($c->estado) {
                                        'atendida', 'completada' => 'background: #dcfce7; color: #15803d;',
                                        'cancelada' => 'background: #fee2e2; color: #b91c1c;',
                                        'inasistencia' => 'background: #fef3c7; color: #b45309;',
                                        'reprogramada' => 'background: #e0e7ff; color: #4338ca;',
                                        'en_atencion', 'en_espera' => 'background: #cffafe; color: #0e7490;',
                                        default => 'background: #f1f5f9; color: #475569;'
                                    };
                                @endphp
                                <span style="display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; {{ $estadoColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $c->estado)) }}
                                </span>
                            </td>
                            <td style="padding: 0.6rem 0.75rem; text-align: center;">
                                @if($c->es_cupo_recuperado)
                                    <span style="background: #e0f2fe; color: #0369a1; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                                        ✓ Sí
                                    </span>
                                @else
                                    <span style="color: var(--color-text-muted); font-size: 0.75rem;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: var(--color-text-muted);">
                                No se encontraron citas para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $citas->links() }}
        </div>
    </div>
</div>

<style>
.sugerencia-btn {
    background: #e2e8f0;
    border: 1px solid #cbd5e1;
    border-radius: 9999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s ease;
}
.sugerencia-btn:hover {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}
@media (max-width: 900px) {
    .constructor-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script src="{{ asset('js/reportes.js') }}" defer
    data-consulta-url="{{ route('reportes.ia.consultar') }}"
    data-transcripcion-url="{{ route('reportes.ia.transcribir') }}"
    data-constructor-url="{{ route('reportes.constructor.preview') }}"
    data-csrf="{{ csrf_token() }}"></script>
@endsection
