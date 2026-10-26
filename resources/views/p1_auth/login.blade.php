<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — MediCita Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 10% 20%, rgba(13, 124, 124, 0.08) 0%, rgba(248, 250, 251, 1) 90%);
            padding: 1.5rem;
        }

        .login-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 440px;
            padding: 2.25rem;
        }

        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }

        .login-brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 10px rgba(13, 124, 124, 0.3);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: var(--font-sm);
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 0.375rem;
        }

        .form-input {
            width: 100%;
            padding: 0.65rem 0.875rem;
            font-size: var(--font-sm);
            font-family: inherit;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius-sm);
            background-color: #FAFAFA;
            color: var(--color-text);
            transition: all var(--transition-fast);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-secondary);
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(13, 124, 124, 0.15);
        }

        .demo-pill {
            font-size: 0.75rem;
            padding: 0.25rem 0.625rem;
            background: #EDF2F7;
            border-radius: 9999px;
            cursor: pointer;
            border: 1px solid #CBD5E0;
            color: var(--color-text);
            transition: all 0.15s ease;
        }

        .demo-pill:hover {
            background: var(--color-secondary);
            color: white;
            border-color: var(--color-secondary);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-brand">
        <div class="login-brand-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
            </svg>
        </div>
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--color-primary); line-height: 1.2;">
                Medi<span style="color: var(--color-secondary);">Cita</span> Pro
            </h1>
            <span style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">
                Sistema de Citas Médicas
            </span>
        </div>
    </div>

    @if(session('success'))
        <div style="background: var(--color-success-bg); border-left: 4px solid var(--color-success); color: var(--color-success); padding: 0.75rem; border-radius: 6px; font-size: 0.875rem; margin-bottom: 1.25rem;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background: var(--color-danger-bg); border-left: 4px solid var(--color-danger); color: var(--color-danger); padding: 0.75rem; border-radius: 6px; font-size: 0.875rem; margin-bottom: 1.25rem;">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div style="background: var(--color-danger-bg); border-left: 4px solid var(--color-danger); color: var(--color-danger); padding: 0.75rem; border-radius: 6px; font-size: 0.875rem; margin-bottom: 1.25rem;">
            @foreach($errors->all() as $err)
                <div>• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form action="{{ route('login.post') }}" method="POST">
        @csrf

        <div class="form-group">
            <label class="form-label" for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" class="form-input" placeholder="ejemplo@medicita.com" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.375rem;">
                <label class="form-label" for="password" style="margin-bottom: 0;">Contraseña</label>
                <span style="font-size: 0.75rem; color: var(--color-text-muted);">Mínimo 8 caracteres</span>
            </div>
            <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: var(--color-text); cursor: pointer;">
                <input type="checkbox" name="remember" style="accent-color: var(--color-secondary);"> Recordar sesión
            </label>
            <span style="font-size: 0.75rem; color: var(--color-text-muted);">Seguridad RBAC</span>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.95rem; font-weight: 600; box-shadow: 0 4px 10px rgba(13, 124, 124, 0.25);">
            Ingresar al Sistema
        </button>
    </form>

    <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--color-border);">
        <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.5rem; text-align: center; text-transform: uppercase;">
            Credenciales de Demostración (Click para autocompletar):
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 0.375rem; justify-content: center;">
            <button type="button" class="demo-pill" style="background: #fef3c7; color: #92400e; border-color: #f59e0b; font-weight: 700;" onclick="fillCreds('superadmin@medicita.com', 'SuperAdmin123!')">
                ⚡ Superusuario
            </button>
            <button type="button" class="demo-pill" onclick="fillCreds('admin@medicita.com', 'Admin123!')">
                🛡 Admin
            </button>
            <button type="button" class="demo-pill" onclick="fillCreds('recepcion@medicita.com', 'Recepcion123!')">
                📋 Recepcionista
            </button>
            <button type="button" class="demo-pill" onclick="fillCreds('dr.perez@medicita.com', 'Medico123!')">
                🩺 Médico
            </button>
            <button type="button" class="demo-pill" onclick="fillCreds('paciente.demo@medicita.com', 'Paciente123!')">
                👤 Paciente
            </button>
        </div>
    </div>
</div>

<script>
function fillCreds(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}
</script>

@include('components.password-visibility')
</body>
</html>
