<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Analítico — MediCita Pro</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5cm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11pt;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0d7c7c;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .logo-title h1 {
            font-size: 18pt;
            color: #0d7c7c;
            margin: 0 0 4px 0;
            font-weight: 700;
        }
        .logo-title p {
            margin: 0;
            font-size: 9pt;
            color: #64748b;
        }
        .meta-info {
            text-align: right;
            font-size: 9pt;
            color: #475569;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }
        .kpi-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 12px;
            background: #f8fafc;
        }
        .kpi-label {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .kpi-val {
            font-size: 14pt;
            font-weight: 700;
            color: #0d7c7c;
            margin: 2px 0;
        }
        .kpi-sub {
            font-size: 8pt;
            color: #475569;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-weight: 600;
            color: #1e293b;
        }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: 600;
        }
        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .signature-box {
            width: 200px;
            text-align: center;
            border-top: 1px solid #64748b;
            padding-top: 6px;
            font-size: 8.5pt;
            color: #475569;
        }
        .no-print {
            background: #0d7c7c;
            color: white;
            padding: 10px 16px;
            text-align: center;
            margin-bottom: 16px;
        }
        .no-print button {
            background: white;
            color: #0d7c7c;
            border: none;
            padding: 6px 14px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <span>Vista previa de impresión oficial de reporte clínico/operacional.</span>
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <div class="header">
        <div class="logo-title">
            <h1>MediCita Pro</h1>
            <p>Sistema Clínico Integrado · Reporte Operacional y de Gestión (CU17)</p>
        </div>
        <div class="meta-info">
            <strong>Fecha de Emisión:</strong> {{ now()->format('d/m/Y H:i') }}<br>
            <strong>Generado por:</strong> {{ auth()->user()->name }} ({{ auth()->user()->rol?->nombre ?? 'Usuario' }})<br>
            <strong>Período:</strong> {{ $filtros['fecha_inicio'] ?? 'Inicio histórico' }} al {{ $filtros['fecha_fin'] ?? 'Hoy' }}
        </div>
    </div>

    <!-- RESUMEN DE INDICADORES / KPIS -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Citas Registradas</div>
            <div class="kpi-val">{{ $kpis['total_citas'] }}</div>
            <div class="kpi-sub">En el período consultado</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Atendidas / Asistencias</div>
            <div class="kpi-val">{{ $kpis['completadas'] }}</div>
            <div class="kpi-sub">{{ $kpis['tasa_asistencia'] }}% tasa de asistencia</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Cancelaciones</div>
            <div class="kpi-val">{{ $kpis['canceladas'] }}</div>
            <div class="kpi-sub">{{ $kpis['tasa_cancelacion'] }}% sobre el total</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Inasistencias (No-Shows)</div>
            <div class="kpi-val">{{ $kpis['inasistencias'] }}</div>
            <div class="kpi-sub">{{ $kpis['tasa_no_show'] }}% tasa de falta</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Cupos Recuperados (Espera)</div>
            <div class="kpi-val">{{ $kpis['cupos_recuperados'] }}</div>
            <div class="kpi-sub">{{ $kpis['tasa_recuperacion'] }}% de cancelaciones recuperadas</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Ocupación de Turnos</div>
            <div class="kpi-val">{{ $kpis['tasa_ocupacion'] }}%</div>
            <div class="kpi-sub">{{ $kpis['slots_reservados'] }} de {{ $kpis['total_slots'] }} slots</div>
        </div>
    </div>

    <!-- DETALLE OPERACIONAL DE CITAS -->
    <h3 style="font-size: 10.5pt; color: #0d7c7c; margin: 16px 0 6px 0;">Muestra Detallada de Citas (Máx. 200 registros)</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 75px;">Ficha</th>
                <th style="width: 70px;">Fecha</th>
                <th style="width: 45px;">Hora</th>
                <th>Especialidad</th>
                <th>Médico</th>
                <th>Paciente</th>
                <th style="width: 65px;">Estado</th>
                <th style="width: 55px; text-align: center;">Recuperado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($citas as $c)
                <tr>
                    <td style="font-family: monospace; font-weight: 600;">{{ $c->codigo_ficha }}</td>
                    <td>{{ $c->fecha ? $c->fecha->format('d/m/Y') : '' }}</td>
                    <td>{{ substr($c->hora, 0, 5) }}</td>
                    <td>{{ $c->medico?->especialidad?->nombre ?? 'N/A' }}</td>
                    <td>Dr(a). {{ $c->medico?->user?->name ?? 'N/A' }}</td>
                    <td>{{ $c->paciente ? trim($c->paciente->nombres.' '.$c->paciente->apellidos) : 'N/A' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $c->estado)) }}</td>
                    <td style="text-align: center;">{{ $c->es_cupo_recuperado ? 'SÍ' : 'NO' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: #64748b; padding: 12px;">
                        No existen registros para los criterios de filtro aplicados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="signature-box">
            Responsable de Reportes / Emisión<br>
            <strong>{{ auth()->user()->name }}</strong>
        </div>
        <div class="signature-box">
            Dirección Médica / Auditoría Clínica<br>
            MediCita Pro
        </div>
    </div>

</body>
</html>
