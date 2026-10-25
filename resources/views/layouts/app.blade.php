<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'MediCita Pro' }} — Sistema de Gestión de Citas Médicas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
