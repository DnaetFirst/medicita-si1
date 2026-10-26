@php
    $currentRoute = request()->path();
@endphp

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="/" class="sidebar-logo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
            </svg>
            Medi<span>Cita</span> Pro
        </a>
    </div>

    <nav class="sidebar-nav">
        <a href="/" class="nav-item {{ $currentRoute === '/' || $currentRoute === 'dashboard' ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
            Dashboard
        </a>

        @if(!auth()->user()->isPaciente())
        <div class="nav-section-title">P4 · Citas Médicas</div>
        <a href="/citas" class="nav-item {{ str_starts_with($currentRoute, 'citas') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
            Gestionar Citas
        </a>
        <a href="/citas/nueva" class="nav-item {{ $currentRoute === 'citas/nueva' ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
            Solicitar Ficha (CU8)
        </a>

        <div class="nav-section-title">P2 · Médicos y Horarios</div>
        <a href="/agenda" class="nav-item {{ str_starts_with($currentRoute, 'agenda') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
            Agenda Médica (CU12)
        </a>
        @if(auth()->user()->isAdmin() || auth()->user()->isRecepcionista())
        <a href="/medicos" class="nav-item {{ str_starts_with($currentRoute, 'medicos') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Médicos (CU4)
        </a>
        <a href="/especialidades" class="nav-item {{ str_starts_with($currentRoute, 'especialidades') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>
            Especialidades (CU3)
        </a>
        <a href="/horarios" class="nav-item {{ str_starts_with($currentRoute, 'horarios') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Horarios & Slots (CU5)
        </a>
        <a href="/bloqueos" class="nav-item {{ str_starts_with($currentRoute, 'bloqueos') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"/></svg>
            Bloqueos de Agenda (CU6)
        </a>

        @endif
        <div class="nav-section-title">P3 · Pacientes</div>
        <a href="/pacientes" class="nav-item {{ str_starts_with($currentRoute, 'pacientes') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Pacientes (CU7)
        </a>

        @endif
        @if(auth()->user()->isAdmin())
        <div class="nav-section-title">P1 · Seguridad y Control</div>
        <a href="/usuarios" class="nav-item {{ str_starts_with($currentRoute, 'usuarios') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Usuarios & Roles (CU2)
        </a>
        <a href="/bitacora" class="nav-item {{ str_starts_with($currentRoute, 'bitacora') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            Bitácora Auditoría (CU19)
        </a>
        @endif
    </nav>
</aside>

<div class="backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.toggle('open');
    backdrop.classList.toggle('open');
}
</script>
