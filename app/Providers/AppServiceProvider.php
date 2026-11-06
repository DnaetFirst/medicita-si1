<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // Usar la paginación personalizada del sistema MediCita Pro
        Paginator::defaultView('vendor.pagination.medicita');
        Paginator::defaultSimpleView('vendor.pagination.medicita');
    }
}
