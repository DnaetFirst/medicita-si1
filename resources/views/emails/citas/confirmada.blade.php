<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Ficha Médica</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #0d7c7c, #065e5e); padding: 24px 32px; color: white;">
            <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">
                Medi<span style="color: #6ee7b7;">Cita</span> Pro
            </div>
            <div style="font-size: 13px; opacity: 0.9; margin-top: 4px;">
                Centro Médico de Especialidades &bull; UAGRM
            </div>
        </div>

        <!-- Body -->
        <div style="padding: 32px;">
            <div style="display: inline-block; background: #ecfdf5; color: #065f46; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; text-transform: uppercase; margin-bottom: 12px;">
                Cita Programada Exitosamente
            </div>
            
            <h2 style="margin: 0 0 12px 0; color: #0d7c7c; font-size: 22px;">
                ¡Hola, {{ $cita->paciente?->nombres }}!
            </h2>
            <p style="margin: 0 0 20px 0; font-size: 15px; color: #475569; line-height: 1.5;">
                Tu turno médico ha sido reservado y confirmado en el sistema. A continuación encontrarás el resumen de tu atención médica:
            </p>

            <!-- Card de detalles -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                    <span style="color: #64748b; font-size: 13px;">Código de Ficha:</span>
                    <strong style="font-family: monospace; color: #0d7c7c; font-size: 15px;">{{ $cita->codigo_ficha }}</strong>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: #64748b; font-size: 13px; display: block;">Especialidad:</span>
                    <strong style="color: #0f172a; font-size: 16px;">{{ $cita->medico?->especialidad?->nombre }}</strong>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: #64748b; font-size: 13px; display: block;">Médico Tratante:</span>
                    <strong style="color: #0f172a; font-size: 15px;">{{ $cita->medico?->user?->name }}</strong>
                    <span style="color: #64748b; font-size: 12px;">(Matrícula: {{ $cita->medico?->nro_registro_medico }})</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: #64748b; font-size: 13px; display: block;">Fecha y Turno Asignado:</span>
                    <strong style="color: #0d7c7c; font-size: 16px;">{{ $cita->fecha ? $cita->fecha->format('d/m/Y') : '' }} a las {{ substr($cita->hora, 0, 5) }} hrs</strong>
                    <span style="color: #64748b; font-size: 12px; display: block;">(Intervalo de consulta: 15 minutos)</span>
                </div>
                @if($cita->motivo_consulta)
                    <div>
                        <span style="color: #64748b; font-size: 13px; display: block;">Motivo de Consulta:</span>
                        <span style="color: #334155; font-size: 14px; font-style: italic;">"{{ $cita->motivo_consulta }}"</span>
                    </div>
                @endif
            </div>

            <!-- Instrucciones -->
            <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 14px 16px; border-radius: 6px; font-size: 13px; color: #1e40af; line-height: 1.5; margin-bottom: 24px;">
                <strong>Recomendación Importante:</strong> Por favor preséntate en ventanilla de recepción 15 minutos antes de tu hora para realizar tu <strong>Check-in</strong> con tu Cédula de Identidad original.
            </div>

            <p style="margin: 0; font-size: 13px; color: #94a3b8; text-align: center;">
                MediCita Pro &bull; Gestión Hospitalaria Integral &bull; Este es un correo automático de notificación
            </p>
        </div>
    </div>
</body>
</html>
