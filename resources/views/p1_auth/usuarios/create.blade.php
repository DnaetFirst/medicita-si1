@extends('layouts.app')

@section('content')
<div style="max-width: 720px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
                Registrar Nuevo Usuario (CU2)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
                Crea una cuenta en el sistema y asigna su rol correspondiente
            </p>
        </div>
        <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">
            ← Volver al Listado
        </a>
    </div>

    @if($errors->any())
        <div class="card" style="background: var(--color-danger-bg); border-color: var(--color-danger); color: var(--color-danger); padding: 0.75rem 1rem;">
            @foreach($errors->all() as $err)
                <div>• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <form action="{{ route('usuarios.store') }}" method="POST">
            @csrf

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="name">Nombre Completo *</label>
                    <input type="text" name="name" id="name" class="form-input" value="{{ old('name') }}" placeholder="Ej: Dr. Fernando Suárez" required>
                </div>
                <div>
                    <label class="form-label" for="ci">Cédula de Identidad (CI) *</label>
                    <input type="text" name="ci" id="ci" class="form-input" value="{{ old('ci') }}" placeholder="Ej: 8492019" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="email">Correo Electrónico *</label>
                    <input type="email" name="email" id="email" class="form-input" value="{{ old('email') }}" placeholder="usuario@medicita.com" required>
                </div>
                <div>
                    <label class="form-label" for="telefono">Teléfono / Celular</label>
                    <input type="text" name="telefono" id="telefono" class="form-input" value="{{ old('telefono') }}" placeholder="Ej: 71234567">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="rol_id">Rol Asignado (RBAC) *</label>
                    <select name="rol_id" id="rol_id" class="form-input" required>
                        <option value="">Selecciona un rol</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" {{ old('rol_id') == $r->id ? 'selected' : '' }}>
                                {{ $r->nombre }} ({{ $r->descripcion }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="estado">Estado Inicial *</label>
                    <select name="estado" id="estado" class="form-input" required>
                        <option value="activo" {{ old('estado', 'activo') === 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="inactivo" {{ old('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label class="form-label" for="password">Contraseña *</label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Mínimo 8 caracteres" required>
                </div>
                <div>
                    <label class="form-label" for="password_confirmation">Confirmar Contraseña *</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-input" placeholder="Repite la contraseña" required>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
                <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">
                    Guardar Usuario
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
