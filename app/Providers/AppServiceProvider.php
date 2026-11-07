<?php

namespace App\Providers;

use App\Mail\BrevoTransport;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('brevo', fn (array $config): BrevoTransport => new BrevoTransport((string) ($config['key'] ?? '')));
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $url = rtrim(config('app.url'), '/').route('password.reset', [
                'token' => $token, 'email' => $user->getEmailForPasswordReset(),
            ], false);

            return (new MailMessage)
                ->subject('Recupera tu contraseña — MediCita Pro')
                ->greeting('Hola, '.$user->name)
                ->line('Recibimos una solicitud para recuperar tu contraseña.')
                ->action('Restablecer contraseña', $url)
                ->line('Este enlace caduca en '.config('auth.passwords.users.expire').' minutos y solo se puede usar una vez.')
                ->line('Si no solicitaste este cambio, puedes ignorar este correo.');
        });
        // Usar la paginación personalizada del sistema MediCita Pro
        Paginator::defaultView('vendor.pagination.medicita');
        Paginator::defaultSimpleView('vendor.pagination.medicita');
    }
}
