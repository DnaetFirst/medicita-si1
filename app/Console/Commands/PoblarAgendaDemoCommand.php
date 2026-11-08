<?php

namespace App\Console\Commands;

use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\User;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PoblarAgendaDemoCommand extends Command
{
    protected $signature = 'app:poblar-agenda-demo';

    protected $description = 'Puebla la agenda de hoy, mañana y los próximos días con slots y citas confirmadas para pruebas de cancelación y lista de espera';

    public function handle(SlotGeneratorService $slotService): int
    {
        $this->info('Iniciando poblamiento integral de agenda para pruebas clínicas...');

        // 1. Asegurar roles esenciales
        $rolMed = Rol::firstOrCreate(['slug' => 'medico'], ['nombre' => 'Médico']);
        $rolPac = Rol::firstOrCreate(['slug' => 'paciente'], ['nombre' => 'Paciente']);

        // 2. Asegurar Especialidad y Médicos principales
        $espGeneral = Especialidad::firstOrCreate(
            ['codigo' => 'MED-GEN'],
            ['nombre' => 'Medicina General', 'activo' => true]
        );

        $userMed1 = User::firstOrCreate(
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

        $medicoGeneral = Medico::firstOrCreate(
            ['user_id' => $userMed1->id],
            [
                'especialidad_id' => $espGeneral->id,
                'nro_registro_medico' => 'COL-MED-84920',
                'telefono_contacto' => '73456789',
                'biografia' => 'Especialista en Medicina Familiar con 12 años de trayectoria clínica.',
                'estado' => 'activo',
            ]
        );

        // 3. Asegurar Paciente Demo (para el usuario que prueba la lista de espera)
        $userPacDemo = User::firstOrCreate(
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
        $pacienteDemo = Paciente::firstOrCreate(
            ['user_id' => $userPacDemo->id],
            [
                'ci' => '40596877',
                'nombres' => 'Juan Pablo',
                'apellidos' => 'Rojas Silva',
                'fecha_nacimiento' => '1990-05-15',
                'genero' => 'masculino',
                'telefono' => '74567890',
                'activo' => true,
            ]
        );

        // 4. Asegurar Pacientes con Citas (María Ballesteros y otros)
        $otrosPacientesData = [
            ['ci' => '6614579365', 'nombres' => 'María', 'apellidos' => 'Ballesteros', 'email' => 'pac.6614579365@medicita.local'],
            ['ci' => '60891234', 'nombres' => 'Lucía', 'apellidos' => 'Fernández Paredes', 'email' => 'lucia.fernandez@gmail.com'],
            ['ci' => '71239845', 'nombres' => 'Mateo', 'apellidos' => 'Villagómez Hurtado', 'email' => 'mateo.villagomez@gmail.com'],
            ['ci' => '52341980', 'nombres' => 'Camila', 'apellidos' => 'Antelo Justiniano', 'email' => 'camila.antelo@gmail.com'],
        ];

        $pacientesParaCitas = [];
        foreach ($otrosPacientesData as $pd) {
            $u = User::firstOrCreate(
                ['email' => $pd['email']],
                [
                    'name' => "{$pd['nombres']} {$pd['apellidos']}",
                    'rol_id' => $rolPac->id,
                    'ci' => $pd['ci'],
                    'password' => Hash::make('Paciente123!'),
                    'estado' => 'activo',
                ]
            );
            $p = Paciente::firstOrCreate(
                ['user_id' => $u->id],
                [
                    'ci' => $pd['ci'],
                    'nombres' => $pd['nombres'],
                    'apellidos' => $pd['apellidos'],
                    'fecha_nacimiento' => '1995-08-20',
                    'genero' => 'femenino',
                    'activo' => true,
                ]
            );
            $pacientesParaCitas[] = $p;
        }

        // 5. Todos los médicos activos a poblar (Dr. Pérez siempre primero)
        $medicos = Medico::with('user', 'especialidad')->where('estado', 'activo')->get();
        if ($medicos->isEmpty()) {
            $medicos = collect([$medicoGeneral]);
        }

        // Fechas: Hoy, Mañana, +2 días y +3 días
        $fechas = [
            today()->toDateString(),
            today()->addDay()->toDateString(),
            today()->addDays(2)->toDateString(),
            today()->addDays(3)->toDateString(),
        ];

        $citasNuevas = 0;

        foreach ($medicos as $med) {
            // Asegurar horarios de atención de lunes a sábado (días 1 a 6)
            for ($dia = 1; $dia <= 6; $dia++) {
                HorarioAtencion::firstOrCreate(
                    ['medico_id' => $med->id, 'dia_semana' => $dia],
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

            foreach ($fechas as $fecha) {
                // Generar los slots canónicos vía SlotGeneratorService
                $slots = $slotService->generarSlotsParaFecha($med, $fecha);

                // Poblar las primeras franjas matutinas (08:00 a 09:00) con citas confirmadas
                $slotsMatutinos = $slots->filter(function ($s) {
                    $hora = substr($s->hora_inicio, 0, 5);
                    return $hora >= '08:00' && $hora < '09:00';
                })->values();

                foreach ($slotsMatutinos as $idx => $slot) {
                    $citaExistente = Cita::where('slot_id', $slot->id)->first();
                    if (! $citaExistente) {
                        $pacienteAsignado = $pacientesParaCitas[$idx % count($pacientesParaCitas)];
                        $slot->update(['estado' => 'reservado']);

                        $codigo = 'FCH-'.str_replace('-', '', $fecha).'-'.strtoupper(Str::random(6));
                        Cita::create([
                            'codigo_ficha' => $codigo,
                            'paciente_id' => $pacienteAsignado->id,
                            'medico_id' => $med->id,
                            'slot_id' => $slot->id,
                            'fecha' => $fecha,
                            'hora' => $slot->hora_inicio,
                            'estado' => 'confirmada',
                            'motivo_consulta' => 'Control médico matutino programado',
                        ]);
                        $citasNuevas++;
                    }
                }
            }
        }

        $this->info("Poblamiento finalizado con éxito. Médicos procesados: {$medicos->count()}. Se aseguraron slots y se generaron {$citasNuevas} citas para cancelación/pruebas.");

        return Command::SUCCESS;
    }
}
