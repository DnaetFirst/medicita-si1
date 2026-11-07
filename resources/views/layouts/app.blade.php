<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'MediCita Pro' }} — Sistema de Gestión de Citas Médicas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        /* Inline fallback in case assets not built */
        @import url('/resources/css/variables.css');
        @import url('/resources/css/app.css');
    </style>
</head>
<body>
    <div class="app-container">
        @include('components.sidebar')

        <div class="main-content">
            <header class="topbar">
                <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Abrir Menú">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>

                <div style="font-weight: 600; font-size: var(--font-lg); color: var(--color-primary);">
                    {{ $header ?? 'MediCita Pro' }}
                </div>

                <div style="display: flex; align-items: center; gap: 1rem;">
                    @auth
                        <div style="text-align: right;">
                            <div style="font-size: var(--font-sm); font-weight: 600; color: var(--color-primary);">{{ Auth::user()->name }}</div>
                            <div style="font-size: var(--font-xs); color: var(--color-text-muted);">{{ Auth::user()->rol?->nombre ?? 'Usuario' }}</div>
                        </div>
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--color-secondary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                            {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                        </div>

                        <form action="{{ route('logout') }}" method="POST" style="margin-left: 0.5rem;">
                            @csrf
                            <button type="submit" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" title="Cerrar Sesión">
                                Salir
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.85rem;">
                            Iniciar Sesión
                        </a>
                    @endauth
                </div>
            </header>

            <main class="page-body">
                @if(session('success_html'))
                    <div class="card" style="background: var(--color-success-bg, #f0fdf4); border-color: var(--color-success, #16a34a); color: var(--color-success, #16a34a); padding: 0.85rem 1.25rem; line-height: 1.6;">
                        {!! session('success_html') !!}
                    </div>
                @endif

                @if(session('success'))
                    <div class="card" style="background: var(--color-success-bg); border-color: var(--color-success); color: var(--color-success); padding: 0.75rem 1rem;">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="card" style="background: var(--color-danger-bg); border-color: var(--color-danger); color: var(--color-danger); padding: 0.75rem 1rem;">
                        {{ session('error') }}
                    </div>
                @endif

                @if(session('warning'))
                    <div class="card" role="status">{{ session('warning') }}</div>
                @endif
                @if($errors->any())
                    <div class="card" role="alert">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @if(Auth::check() && Auth::user()->debe_cambiar_password)
                    <div class="card" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border: 1px solid #f59e0b; color: #92400e; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; border-radius: var(--border-radius-md); box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">⚠️</span>
                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: #78350f;">Aviso de Seguridad: Contraseña Inicial por Defecto</div>
                                <div style="font-size: 0.85rem; color: #92400e;">Has iniciado sesión con tu contraseña inicial (número de carnet de identidad). Por tu seguridad y privacidad, te recomendamos cambiarla por una contraseña personal.</div>
                            </div>
                        </div>
                        <button type="button" class="btn" onclick="document.getElementById('modalCambioPassword').style.display = 'flex'" style="background: #f59e0b; color: white; border: none; font-weight: 600; font-size: 0.85rem; padding: 0.5rem 1rem; border-radius: var(--border-radius-sm); cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Cambiar Mi Contraseña Ahora
                        </button>
                    </div>

                    <!-- Modal para cambiar contraseña -->
                    <div id="modalCambioPassword" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
                        <div class="card" style="max-width: 440px; width: 100%; background: white; padding: 1.75rem; border-radius: var(--border-radius-lg); box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h3 style="margin: 0; font-size: 1.15rem; color: var(--color-primary); font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                                    <span>🔑</span> Actualizar Contraseña
                                </h3>
                                <button type="button" onclick="document.getElementById('modalCambioPassword').style.display = 'none'" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1.25rem;">
                                Ingresa tu nueva contraseña para reemplazar el número de carnet inicial.
                            </p>
                            <form method="POST" action="{{ route('perfil.password') }}">
                                @csrf
                                <div style="margin-bottom: 1rem;">
                                    <label class="form-label">Nueva Contraseña (mínimo 6 caracteres)</label>
                                    <input type="password" name="password" class="form-input" required minlength="6" placeholder="••••••••">
                                </div>
                                <div style="margin-bottom: 1.5rem;">
                                    <label class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" name="password_confirmation" class="form-input" required minlength="6" placeholder="••••••••">
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalCambioPassword').style.display = 'none'">Cancelar</button>
                                    <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem;">Guardar y Proteger Cuenta</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @include('components.password-visibility')
</body>
</html>
