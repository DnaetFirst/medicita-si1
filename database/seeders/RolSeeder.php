<?php

namespace Database\Seeders;

use App\Models\P1\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'Superusuario (Acceso Total)',
                'slug' => 'superadmin',
                'descripcion' => 'Acceso maestro irrestricto a todos los módulos y funciones del sistema',
                'permisos' => ['*'],
                'activo' => true,
            ],
            [
                'nombre' => 'Administrador',
                'slug' => 'admin',
                'descripcion' => 'Acceso administrativo y configuración del sistema',
                'permisos' => ['*'],
                'activo' => true,
            ],
            [
                'nombre' => 'Recepcionista',
                'slug' => 'recepcionista',
                'descripcion' => 'Gestión operativa de citas, médicos y pacientes',
                'permisos' => ['citas.*', 'pacientes.*', 'medicos.view', 'horarios.view'],
                'activo' => true,
            ],
            [
                'nombre' => 'Médico',
                'slug' => 'medico',
                'descripcion' => 'Atención clínica, consulta de agenda y horarios propios',
                'permisos' => ['agenda.own', 'citas.attend', 'horarios.own'],
                'activo' => true,
            ],
            [
                'nombre' => 'Paciente',
                'slug' => 'paciente',
                'descripcion' => 'Autogestión de citas y consulta de turnos propios',
                'permisos' => ['citas.request', 'citas.cancel_own', 'citas.view_own'],
                'activo' => true,
            ],
        ];

        foreach ($roles as $rol) {
            Rol::firstOrCreate(['slug' => $rol['slug']], $rol);
        }
    }
}
