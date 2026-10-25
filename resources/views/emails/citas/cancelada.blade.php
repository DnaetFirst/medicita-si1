<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cancelación de Cita Médica</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #ef4444, #b91c1c); padding: 24px 32px; color: white;">
            <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">
                Medi<span style="color: #fecaca;">Cita</span> Pro
            </div>
            <div style="font-size: 13px; opacity: 0.9; margin-top: 4px;">
                Centro Médico de Especialidades &bull; UAGRM
            </div>
        </div>

        <!-- Body -->
        <div style="padding: 32px;">
            <div style="display: inline-block; background: #fee2e2; color: #991b1b; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; text-transform: uppercase; margin-bottom: 12px;">
                Aviso de Cancelación de Cita
            </div>
            
            <h2 style="margin: 0 0 12px 0; color: #b91c1c; font-size: 22px;">
                Estimado/a {{ $cita->paciente?->nombres }},
            </h2>
            <p style="margin: 0 0 20px 0; font-size: 15px; color: #475569; line-height: 1.5;">
                Te informamos que tu cita médica con ficha <strong>{{ $cita->codigo_ficha }}</strong> ha sido cancelada y el turno correspondiente ha sido liberado en el sistema.
            </p>

            <!-- Card de detalles -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <div style="margin-bottom: 10px;">
                    <span style="color: #64748b; font-size: 13px; display: block;">Turno Cancelado:</span>
                    <strong style="color: #64748b; font-size: 15px;">{{ $cita->fecha ? $cita->fecha->format('d/m/Y') : '' }} a las {{ substr($cita->hora, 0, 5) }} hrs</strong>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: #64748b; font-size: 13px; display: block;">Médico y Especialidad:</span>
                    <strong style="color: #0f172a; font-size: 15px;">{{ $cita->medico?->user?->name }}</strong>
                    <span style="color: #64748b; font-size: 13px;">({{ $cita->medico?->especialidad?->nombre }})</span>
                </div>
                <div>
                    <span style="color: #b91c1c; font-size: 13px; font-weight: 700; display: block;">Motivo de Cancelación:</span>
                    <span style="color: #334155; font-size: 14px; font-style: italic;">"{{ $motivo }}"</span>
                </div>
            </div>

            <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 6px; font-size: 13px; color: #92400e; line-height: 1.5; margin-bottom: 24px;">
                Si deseas reprogramar una nueva cita con otro médico o en un horario diferente, puedes comunicarte con recepción o ingresar a nuestra plataforma web.
            </div>

            <p style="margin: 0; font-size: 13px; color: #94a3b8; text-align: center;">
                MediCita Pro &bull; Gestión Hospitalaria Integral
            </p>
        </div>
    </div>
</body>
</html>
