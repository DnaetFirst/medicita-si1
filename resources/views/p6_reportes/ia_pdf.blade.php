<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ mb_substr($reporte->pregunta_usuario ?? 'Reporte IA', 0, 60) }} · MediCita</title>
<style>
body{font:14px system-ui,sans-serif;color:#163047;margin:24px}
.header{background:#1e40af;color:white;padding:14px 20px;border-radius:8px 8px 0 0;margin-bottom:0}
.header h1{margin:0;font-size:1.1rem;font-weight:700}
.header .meta{font-size:0.75rem;color:#bfdbfe;margin-top:4px}
.subtitle{background:#eff6ff;border:1px solid #bfdbfe;border-top:0;padding:10px 20px;border-radius:0 0 6px 6px;font-size:0.82rem;color:#1e40af;margin-bottom:16px}
table{width:100%;border-collapse:collapse}
th{background:#1d4ed8;color:white;text-align:left;padding:8px 10px;font-size:0.82rem}
td{text-align:left;padding:7px 10px;font-size:0.82rem;border-bottom:1px solid #dbeafe;overflow-wrap:anywhere}
tr:nth-child(even) td{background:#eff6ff}
thead{display:table-header-group}
tr{break-inside:avoid}
pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#0f172a;color:#38bdf8;padding:10px;border-radius:4px;font-size:0.75rem}
.actions{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:16px}
.btn{display:inline-flex;align-items:center;gap:0.4rem;padding:8px 16px;border-radius:6px;font:inherit;text-decoration:none;cursor:pointer;border:none;font-size:0.85rem;font-weight:600}
.btn-primary{background:#1d4ed8;color:white}
.btn-secondary{background:#e2e8f0;color:#334155}
@media print{
    .actions{display:none}
    body{margin:0;font-size:10px}
    @page{size:A4 landscape;margin:14mm}
}
</style>
</head>
<body>
<div class="actions">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
    <a class="btn btn-secondary" href="{{ route('reportes.index') }}">← Volver a Reportes</a>
</div>

<div class="header">
    <h1>✨ Reporte IA — MediCita Pro</h1>
    <div class="meta">Generado el {{ $reporte->created_at->format('d/m/Y H:i') }} · {{ count($snapshot['filas']) }} {{ count($snapshot['filas']) === 1 ? 'registro' : 'registros' }}</div>
</div>
<div class="subtitle">
    <strong>Consulta:</strong> {{ $reporte->pregunta_usuario }}
</div>

@if(count($snapshot['filas']) > 0)
<table>
    <thead>
        <tr>
            @foreach($snapshot['columnas'] as $columna)
            <th>{{ str_replace('_', ' ', $columna) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($snapshot['filas'] as $fila)
        <tr>
            @foreach($snapshot['columnas'] as $columna)
            <td>{{ $fila[$columna] ?? '—' }}</td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p style="color:#6b7280;text-align:center;padding:2rem;">Sin resultados para esta consulta.</p>
@endif

<details style="margin-top:1rem">
    <summary style="cursor:pointer;font-weight:600;color:#2563eb;font-size:0.85rem">🔍 Transparencia: Consulta SQL validada</summary>
    <pre>{{ $reporte->sql_generado }}</pre>
</details>
</body>
</html>
