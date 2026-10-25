<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reprogramación de Cita Médica</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #0284c7, #0369a1); padding: 24px 32px; color: white;">
            <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">
                Medi<span style="color: #93c5fd;">Cita</span> Pro
            </div>
            <div style="font-size: 13px; opacity: 0.9; margin-top: 4px;">
                Centro Médico de Especialidades &bull; UAGRM
            </div>
        </div>

        <!-- Body -->
        <div style="padding: 32px;">
            <div style="display: inline-block; background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; text-transform: uppercase; margin-bottom: 12px;">
                Cita Médica Reprogramada
            </div>
            
            <h2 style="margin: 0 0 12px 0; color: #0284c7; font-size: 22px;">
                Estimado/a {{ $citaNueva->paciente?->nombres }},
            </h2>
            <p style="margin: 0 0 20px 0; font-size: 15px; color: #475569; line-height: 1.5;">
                Te informamos que tu cita médica ha sido reprogramada satisfactoriamente con la nueva ficha <strong>{{ $citaNueva->codigo_ficha }}</strong>.
            </p>

            <!-- Card de detalles -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <div style="margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                    <span style="color: #64748b; font-size: 13px;">Turno Anterior (Cancelado):</span>
                    <div style="color: #ef4444; text-decoration: line-through; font-size: 14px;">
                        {{ $citaAnterior->fecha ? $citaAnterior->fecha->format('d/m/Y') : '' }} a las {{ substr($citaAnterior->hora, 0, 5) }} hrs
                    </div>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <span style="color: #0369a1; font-size: 13px; font-weight: 700; display: block; text-transform: uppercase;">Nuevo Turno Asignado:</span>
                    <strong style="color: #0284c7; font-size: 18px;">{{ $citaNueva->fecha ? $citaNueva->fecha->format('d/m/Y') : '' }} a las {{ substr($citaNueva->hora, 0, 5) }} hrs</strong>
                </div>

                <div style="margin-bottom: 10px;">
                    <span style="color: #64748b; font-size: 13px; display: block;">Médico y Especialidad:</span>
                    <strong style="color: #0f172a; font-size: 15px;">{{ $citaNueva->medico?->user?->name }}</strong>
                    <span style="color: #64748b; font-size: 13px;">({{ $citaNueva->medico?->especialidad?->nombre }})</span>
                </div>
            </div>

            <div style="background: #eff6ff; border-left: 4px solid #0284c7; padding: 14px 16px; border-radius: 6px; font-size: 13px; color: #1e40af; line-height: 1.5; margin-bottom: 24px;">
                Recuerda presentarte 15 minutos antes con tu Cédula de Identidad en ventanilla de recepción para realizar tu Check-in.
            </div>

            <p style="margin: 0; font-size: 13px; color: #94a3b8; text-align: center;">
                MediCita Pro &bull; Gestión Hospitalaria Integral
            </p>
        </div>
    </div>
</body>
</html>
