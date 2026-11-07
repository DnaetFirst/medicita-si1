<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $aviso->titulo }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 20px;">
    <div style="max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div style="background: #0d7c7c; color: #ffffff; padding: 20px 24px; text-align: left;">
            <div style="font-size: 18px; font-weight: 700; letter-spacing: -0.5px;">MediCita Pro</div>
            <div style="font-size: 13px; opacity: 0.9; margin-top: 2px;">Sistema Clínico Integrado</div>
        </div>
        <div style="padding: 24px;">
            <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 12px;">{{ $aviso->titulo }}</h2>
            <p style="font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 24px;">{{ $aviso->mensaje }}</p>
            <div style="margin-bottom: 24px;">
                @if($aviso->cita_id)
                    <a href="{{ route('citas.show', $aviso->cita_id) }}" style="display: inline-block; background: #0d7c7c; color: #ffffff; font-weight: 600; font-size: 14px; padding: 10px 18px; border-radius: 6px; text-decoration: none;">Ver Detalles en el Portal</a>
                @else
                    <a href="{{ url('/') }}" style="display: inline-block; background: #0d7c7c; color: #ffffff; font-weight: 600; font-size: 14px; padding: 10px 18px; border-radius: 6px; text-decoration: none;">Abrir Portal Clínico</a>
                @endif
            </div>
            <p style="font-size: 12px; color: #64748b; line-height: 1.5; border-top: 1px solid #e2e8f0; padding-top: 16px; margin-bottom: 0;">
                Este es un mensaje automático generado por el sistema de notificaciones de MediCita Pro. Por seguridad y confidencialidad médica, no respondas a este correo.
            </p>
        </div>
    </div>
</body>
</html>
