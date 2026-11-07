<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña — MediCita Pro</title>
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; background: var(--color-background, #f8fafb); }
        .recovery-card { width: 100%; max-width: 440px; padding: 2rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-xl); }
        .form-group { margin: 1.25rem 0; }
        .form-label { display: block; margin-bottom: .5rem; }
        .form-input { width: 100%; padding: .75rem; border: 1px solid var(--color-border); border-radius: var(--border-radius-sm); }
        .recovery-message { margin: 1rem 0; padding: .75rem; border-radius: 6px; }
    </style>
</head>
<body>
<main class="recovery-card">
    <h1 style="color: var(--color-primary);">{{ isset($token) ? 'Nueva contraseña' : '¿Olvidaste tu contraseña?' }}</h1>
    <p>{{ isset($token) ? 'Elige una contraseña con al menos 8 caracteres, mayúsculas, minúsculas, números y símbolos.' : 'Escribe el correo registrado en MediCita Pro para recibir un enlace de recuperación.' }}</p>
    @if(session('success'))
        <div role="status" class="recovery-message" style="background: var(--color-success-bg);">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="recovery-message" style="background: var(--color-danger-bg);">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route(isset($token) ? 'password.update' : 'password.email') }}">
        @csrf
        @isset($token)
            <input type="hidden" name="token" value="{{ $token }}">
        @endisset
        <div class="form-group">
            <label class="form-label" for="email">Correo electrónico</label>
            <input class="form-input" type="email" name="email" id="email" value="{{ old('email', $email ?? '') }}" required autocomplete="email" autofocus>
        </div>
        @isset($token)
            <div class="form-group">
                <label class="form-label" for="password">Nueva contraseña</label>
                <input class="form-input" type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                <input class="form-input" type="password" name="password_confirmation" id="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
        @endisset
        <button class="btn btn-primary" type="submit" style="width: 100%;">{{ isset($token) ? 'Guardar contraseña' : 'Enviar enlace de recuperación' }}</button>
    </form>
    <p style="margin-top: 1.5rem; text-align: center;"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
</main>
@include('components.password-visibility')
</body>
</html>
