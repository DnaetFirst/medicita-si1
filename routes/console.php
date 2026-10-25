<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('make:superadmin {--email=superadmin@medicita.com} {--password=SuperAdmin123!} {--name=Superusuario Maestro}', function () {
    $email = $this->option('email');
    $password = $this->option('password');
    $name = $this->option('name');

    $rol = \App\Models\P1\Rol::firstOrCreate(
        ['slug' => 'superadmin'],
        [
            'nombre' => 'Superusuario (Acceso Total)',
            'descripcion' => 'Acceso maestro irrestricto a todos los módulos y funciones del sistema',
            'permisos' => ['*'],
            'activo' => true,
        ]
    );

    $user = \App\Models\User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'rol_id' => $rol->id,
            'ci' => '10000001',
            'telefono' => '70000000',
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'estado' => 'activo',
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]
    );

    $this->info("✅ Superusuario configurado exitosamente:");
    $this->line("• Nombre:   {$user->name}");
    $this->line("• Email:    {$user->email}");
    $this->line("• Password: {$password}");
    $this->line("• Rol:      {$rol->nombre} ({$rol->slug})");
})->purpose('Crea o actualiza el superusuario maestro con acceso total');
