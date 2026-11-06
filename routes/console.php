<?php

use App\Jobs\EnviarAviso;
use App\Models\P1\Rol;
use App\Models\P7\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('make:superadmin {--email=superadmin@medicita.com} {--password=SuperAdmin123!} {--name=Superusuario Maestro}', function () {
    $email = $this->option('email');
    $password = $this->option('password');
    $name = $this->option('name');

    $rol = Rol::firstOrCreate(
        ['slug' => 'superadmin'],
        [
            'nombre' => 'Superusuario (Acceso Total)',
            'descripcion' => 'Acceso maestro irrestricto a todos los módulos y funciones del sistema',
            'permisos' => ['*'],
            'activo' => true,
        ]
    );

    $user = User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'rol_id' => $rol->id,
            'ci' => '10000001',
            'telefono' => '70000000',
            'password' => Hash::make($password),
            'estado' => 'activo',
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]
    );

    $this->info('âœ… Superusuario configurado exitosamente:');
    $this->line("• Nombre:   {$user->name}");
    $this->line("• Email:    {$user->email}");
    $this->line("• Password: {$password}");
    $this->line("• Rol:      {$rol->nombre} ({$rol->slug})");
})->purpose('Crea o actualiza el superusuario maestro con acceso total');

Schedule::call(function (): void {
    Notificacion::whereNull('enviado_at')->whereIn('estado_envio', ['pendiente', 'fallido'])->where('intentos', '<', 5)
        ->whereNotNull('evento_id')->orderBy('id')->chunkById(100, function ($avisos): void {
            foreach ($avisos as $aviso) {
                EnviarAviso::dispatch($aviso->id);
            }
        });
})->name('reintentar-avisos')->everyFiveMinutes()->withoutOverlapping();

Schedule::call(function (): void {
    app(\App\Services\RecuperacionCupoService::class)->procesarOfertasVencidas();
})->name('procesar-ofertas-vencidas')->everyMinute()->withoutOverlapping();
