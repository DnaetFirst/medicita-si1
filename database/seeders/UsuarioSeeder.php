<?php

namespace Database\Seeders;

use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\P3\Paciente;
use App\Models\P3\ResponsableAutorizado;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $rolSuper = Rol::where('slug', 'superadmin')->first();
        $rolAdmin = Rol::where('slug', 'admin')->first();
        $rolRec = Rol::where('slug', 'recepcionista')->first();
        $rolMed = Rol::where('slug', 'medico')->first();
        $rolPac = Rol::where('slug', 'paciente')->first();

        // 0. Superusuario (Acceso Total Irrestricto a Todo el Sistema)
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@medicita.com'],
            [
                'name' => 'Superusuario Root (Acceso Total)',
                'rol_id' => $rolSuper?->id ?? $rolAdmin->id,
                'ci' => '10000001',
                'telefono' => '70000000',
                'password' => Hash::make('SuperAdmin123!'),
                'estado' => 'activo',
            ]
        );

        // 1. Usuario Administrador
        $admin = User::firstOrCreate(
            ['email' => 'admin@medicita.com'],
            [
                'name' => 'Dr. Carlos Mendoza (Admin)',
                'rol_id' => $rolAdmin->id,
                'ci' => '10293847',
                'telefono' => '71234567',
                'password' => Hash::make('Admin123!'),
                'estado' => 'activo',
            ]
        );

        // 2. Usuario Recepcionista
        $recepcionista = User::firstOrCreate(
            ['email' => 'recepcion@medicita.com'],
            [
                'name' => 'Lic. María Elena Torres',
                'rol_id' => $rolRec->id,
                'ci' => '20394857',
                'telefono' => '72345678',
                'password' => Hash::make('Recepcion123!'),
                'estado' => 'activo',
            ]
        );

        // 3. Usuario Médico y Perfil Médico
        $espGeneral = Especialidad::where('codigo', 'MED-GEN')->first();
        $espPediatria = Especialidad::where('codigo', 'PED-01')->first();

        $userMedico1 = User::firstOrCreate(
            ['email' => 'dr.perez@medicita.com'],
            [
                'name' => 'Dr. Roberto Pérez Suárez',
                'rol_id' => $rolMed->id,
                'ci' => '30495867',
                'telefono' => '73456789',
                'password' => Hash::make('Medico123!'),
                'estado' => 'activo',
            ]
        );

        $medico1 = Medico::firstOrCreate(
            ['user_id' => $userMedico1->id],
            [
                'especialidad_id' => $espGeneral->id,
                'nro_registro_medico' => 'COL-MED-84920',
                'telefono_contacto' => '73456789',
                'biografia' => 'Especialista en Medicina Familiar con 12 años de trayectoria clínica.',
                'estado' => 'activo',
            ]
        );

        // Configurar horario de Lunes a Viernes para el Dr. Pérez
        // 08:00 - 17:00 con 1 HORA DE DESCANSO (12:00 - 13:00) y slots de 15 minutos
        for ($dia = 1; $dia <= 5; $dia++) {
            HorarioAtencion::firstOrCreate(
                [
                    'medico_id' => $medico1->id,
                    'dia_semana' => $dia,
                ],
                [
                    'hora_inicio' => '08:00',
                    'hora_fin' => '17:00',
                    'duracion_slot_minutos' => 15,
                    'descanso_inicio' => '12:00',
                    'descanso_fin' => '13:00',
                    'activo' => true,
                ]
            );
        }

        // 4. Usuario Paciente Demo
        $userPaciente = User::firstOrCreate(
            ['email' => 'paciente.demo@medicita.com'],
            [
                'name' => 'Juan Pablo Rojas Silva',
                'rol_id' => $rolPac->id,
                'ci' => '40596877',
                'telefono' => '74567890',
                'password' => Hash::make('Paciente123!'),
                'estado' => 'activo',
            ]
        );

        $paciente = Paciente::firstOrCreate(
            ['ci' => '40596877'],
            [
                'user_id' => $userPaciente->id,
                'nombres' => 'Juan Pablo',
                'apellidos' => 'Rojas Silva',
                'fecha_nacimiento' => '1990-05-15',
                'genero' => 'Masculino',
                'grupo_sanguineo' => 'O+',
                'telefono' => '74567890',
                'email' => 'paciente.demo@medicita.com',
                'direccion' => 'Av. San Martín #450, Santa Cruz',
                'contacto_emergencia_nombre' => 'Ana Silva (Madre)',
                'contacto_emergencia_telefono' => '75678901',
                'activo' => true,
            ]
        );

        // Responsable autorizado con vigencia válida
        ResponsableAutorizado::firstOrCreate(
            ['paciente_id' => $paciente->id, 'ci' => '50697888'],
            [
                'nombres' => 'Ana Silva de Rojas',
                'parentesco' => 'Madre',
                'telefono' => '75678901',
                'fecha_limite_vigencia' => now()->addMonths(6)->toDateString(),
                'activo' => true,
            ]
        );
    }
}
