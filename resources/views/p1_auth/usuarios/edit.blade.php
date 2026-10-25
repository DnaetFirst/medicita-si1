@extends('layouts.app')

@section('content')
<div style="max-width: 720px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
                Modificar Usuario (CU2)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
                Actualiza datos personales, rol o restablece la contraseña
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
        <form action="{{ route('usuarios.update', $usuario) }}" method="POST">
            @csrf
            @method('PUT')

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="name">Nombre Completo *</label>
                    <input type="text" name="name" id="name" class="form-input" value="{{ old('name', $usuario->name) }}" required>
                </div>
                <div>
                    <label class="form-label" for="ci">Cédula de Identidad (CI) *</label>
                    <input type="text" name="ci" id="ci" class="form-input" value="{{ old('ci', $usuario->ci) }}" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="email">Correo Electrónico *</label>
                    <input type="email" name="email" id="email" class="form-input" value="{{ old('email', $usuario->email) }}" required>
                </div>
                <div>
                    <label class="form-label" for="telefono">Teléfono / Celular</label>
                    <input type="text" name="telefono" id="telefono" class="form-input" value="{{ old('telefono', $usuario->telefono) }}">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label" for="rol_id">Rol Asignado (RBAC) *</label>
                    <select name="rol_id" id="rol_id" class="form-input" required>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" {{ old('rol_id', $usuario->rol_id) == $r->id ? 'selected' : '' }}>
                                {{ $r->nombre }} ({{ $r->descripcion }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="estado">Estado *</label>
                    <select name="estado" id="estado" class="form-input" required>
                        <option value="activo" {{ old('estado', $usuario->estado) === 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="inactivo" {{ old('estado', $usuario->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--color-border); margin-bottom: 1.5rem;">
                <h3 style="font-size: var(--font-md); font-weight: 600; color: var(--color-primary); margin-bottom: 0.5rem;">
                    Cambiar Contraseña (Opcional)
                </h3>
                <p style="font-size: var(--font-xs); color: var(--color-text-muted); margin-bottom: 1rem;">
                    Deja estos campos en blanco si no deseas modificar la contraseña actual del usuario.
                </p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label" for="password">Nueva Contraseña</label>
                        <input type="password" name="password" id="password" class="form-input" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div>
                        <label class="form-label" for="password_confirmation">Confirmar Nueva Contraseña</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-input" placeholder="Repite la nueva contraseña">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
                <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">
                    Actualizar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
