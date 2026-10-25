@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Cuentas, Roles y Permisos (CU2)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Administración centralizada de usuarios y asignación de perfiles RBAC
        </p>
    </div>
    <a href="{{ route('usuarios.create') }}" class="btn btn-primary" style="box-shadow: 0 4px 10px rgba(13, 124, 124, 0.2);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo Usuario
    </a>
</div>

<!-- KPIs -->
<div class="grid-4" style="margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Total Usuarios</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-primary);">{{ $kpis['total'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Cuentas Activas</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-success);">{{ $kpis['activos'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Inactivas</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-text-muted);">{{ $kpis['inactivos'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Bloqueados (Fuerza Bruta)</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-danger);">{{ $kpis['bloqueados'] }}</div>
    </div>
</div>

<!-- Filtros y Búsqueda -->
<div class="card" style="padding: 1rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('usuarios.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 2; min-width: 220px;">
            <input type="text" name="search" class="form-input" placeholder="Buscar por CI, nombre o correo..." value="{{ $filters['search'] ?? '' }}" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <select name="rol_id" class="form-input" style="width: 100%;">
                <option value="">Todos los Roles</option>
                @foreach($roles as $r)
                    <option value="{{ $r->id }}" {{ ($filters['rol_id'] ?? '') == $r->id ? 'selected' : '' }}>
                        {{ $r->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <select name="estado" class="form-input" style="width: 100%;">
                <option value="">Todos los Estados</option>
                <option value="activo" {{ ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' }}>Activos</option>
                <option value="inactivo" {{ ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            Filtrar
        </button>
        <a href="{{ route('usuarios.index') }}" class="btn btn-secondary" style="color: var(--color-text-muted);">
            Limpiar
        </a>
    </form>
</div>

<!-- Tabla de Usuarios -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
            <thead>
                <tr style="background: #F8FAFC; border-bottom: 1px solid var(--color-border); color: var(--color-text-muted); font-size: 0.75rem; text-transform: uppercase;">
                    <th style="padding: 0.875rem 1.25rem;">Usuario / CI</th>
                    <th style="padding: 0.875rem 1.25rem;">Correo</th>
                    <th style="padding: 0.875rem 1.25rem;">Rol Asignado</th>
                    <th style="padding: 0.875rem 1.25rem;">Teléfono</th>
                    <th style="padding: 0.875rem 1.25rem;">Estado</th>
                    <th style="padding: 0.875rem 1.25rem; text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $user)
                    <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.15s ease;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='white'">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="font-weight: 600; color: var(--color-primary);">{{ $user->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">CI: {{ $user->ci ?? 'S/N' }}</div>
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--color-text);">
                            {{ $user->email }}
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            <span class="badge" style="background: #E2E8F0; color: #2D3748; font-weight: 600;">
                                {{ $user->rol?->nombre ?? 'Sin Rol' }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--color-text-muted);">
                            {{ $user->telefono ?? '—' }}
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            @if($user->bloqueado_hasta && \Carbon\Carbon::parse($user->bloqueado_hasta)->isFuture())
                                <span class="badge badge-danger">Bloqueado (15m)</span>
                            @elseif($user->estado === 'activo')
                                <span class="badge badge-success">Activo</span>
                            @else
                                <span class="badge" style="background: #E2E8F0; color: #718096;">Inactivo</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <a href="{{ route('usuarios.edit', $user) }}" class="btn btn-secondary" style="padding: 0.25rem 0.625rem; font-size: 0.75rem;">
                                    Editar
                                </a>

                                <form action="{{ route('usuarios.toggle', $user) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.625rem; font-size: 0.75rem; color: {{ $user->estado === 'activo' ? 'var(--color-danger)' : 'var(--color-success)' }};">
                                        {{ $user->estado === 'activo' ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--color-text-muted);">
                            No se encontraron usuarios registrados con los filtros aplicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($usuarios->hasPages())
        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--color-border);">
            {{ $usuarios->links() }}
        </div>
    @endif
</div>
@endsection
