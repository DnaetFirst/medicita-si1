<?php

namespace Database\Seeders;

use App\Models\P1\Bitacora;
use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\AtencionMedica;
use App\Models\P3\Derivacion;
use App\Models\P3\HistoriaClinica;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P4\HistorialCita;
use App\Models\P4\ListaEspera;
use App\Models\P4\OfertaCupo;
use App\Models\P5\DetalleResultado;
use App\Models\P5\ExamenLaboratorio;
use App\Models\P5\OrdenExamen;
use App\Models\P5\OrdenLaboratorio;
use App\Models\P5\ResultadoLaboratorio;
use App\Models\P6\ReporteIa;
use App\Models\P7\Notificacion;
use App\Models\User;
use App\Services\LaboratorioPdfService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemostracionCompletaSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Asegurar catálogo de roles, especialidades y exámenes
        $this->call([
            RolSeeder::class,
            EspecialidadSeeder::class,
            ExamenLaboratorioSeeder::class,
        ]);

        $rolSuper = Rol::where('slug', 'superadmin')->first();
        $rolAdmin = Rol::where('slug', 'admin')->first();
        $rolRec = Rol::where('slug', 'recepcionista')->first();
        $rolMed = Rol::where('slug', 'medico')->first();
        $rolPac = Rol::where('slug', 'paciente')->first();
        $rolLab = Rol::where('slug', 'lab')->first();

        // 2. Personal Administrativo y de Apoyo
        User::firstOrCreate(
            ['email' => 'superadmin@medicita.com'],
            [
                'name' => 'Superusuario Root (Acceso Maestro)',
                'rol_id' => $rolSuper?->id ?? $rolAdmin->id,
                'ci' => '10000001',
                'telefono' => '70000000',
                'password' => Hash::make('SuperAdmin123!'),
                'estado' => 'activo',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@medicita.com'],
            [
                'name' => 'Dr. Carlos Mendoza (Admin General)',
                'rol_id' => $rolAdmin->id,
                'ci' => '10293847',
                'telefono' => '71234567',
                'password' => Hash::make('Admin123!'),
                'estado' => 'activo',
            ]
        );

        $recepcionista1 = User::firstOrCreate(
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

        User::firstOrCreate(
            ['email' => 'recepcion2@medicita.com'],
            [
                'name' => 'Lic. Laura Justiniano Suárez',
                'rol_id' => $rolRec->id,
                'ci' => '20394858',
                'telefono' => '72345679',
                'password' => Hash::make('Recepcion123!'),
                'estado' => 'activo',
            ]
        );

        $bioquimico1 = User::firstOrCreate(
            ['email' => 'lab@medicita.com'],
            [
                'name' => 'Lic. Bioq. Roberto Arnez Morales',
                'rol_id' => $rolLab->id,
                'ci' => '60798899',
                'telefono' => '76789012',
                'password' => Hash::make('Lab123!'),
                'estado' => 'activo',
            ]
        );

        User::firstOrCreate(
            ['email' => 'bioq.aguilera@medicita.com'],
            [
                'name' => 'Lic. Bioq. Marcela Aguilera Claure',
                'rol_id' => $rolLab->id,
                'ci' => '60798898',
                'telefono' => '76789013',
                'password' => Hash::make('Lab123!'),
                'estado' => 'activo',
            ]
        );

        // 3. Plantel Médico y Especialidades
        $espGen = Especialidad::where('codigo', 'MED-GEN')->firstOrFail();
        $espPed = Especialidad::where('codigo', 'PED-01')->firstOrFail();
        $espCard = Especialidad::where('codigo', 'CARD-01')->firstOrFail();
        $espGin = Especialidad::where('codigo', 'GIN-01')->firstOrFail();
        $espOdo = Especialidad::where('codigo', 'ODO-01')->firstOrFail();
        $espGer = Especialidad::where('codigo', 'GER-01')->firstOrFail();

        $medicosData = [
            [
                'email' => 'dr.perez@medicita.com',
                'name' => 'Dr. Roberto Pérez Suárez',
                'ci' => '30495867',
                'telefono' => '73456789',
                'especialidad_id' => $espGen->id,
                'nro_registro' => 'COL-MED-84920',
                'bio' => 'Especialista en Medicina Familiar y Comunitaria con 14 años de trayectoria clínica en atención primaria preventiva.',
            ],
            [
                'email' => 'dra.gomez@medicita.com',
                'name' => 'Dra. Valeria Gómez Céspedes',
                'ci' => '30495868',
                'telefono' => '73456780',
                'especialidad_id' => $espPed->id,
                'nro_registro' => 'COL-MED-92140',
                'bio' => 'Pediatra certificada, especialista en infectología infantil y desarrollo integral del lactante y preescolar.',
            ],
            [
                'email' => 'dr.vargas@medicita.com',
                'name' => 'Dr. Fernando Vargas Montenegro',
                'ci' => '30495869',
                'telefono' => '73456781',
                'especialidad_id' => $espCard->id,
                'nro_registro' => 'COL-MED-77312',
                'bio' => 'Cardiólogo clínico intervencionista, miembro de la Sociedad Boliviana de Cardiología, experto en HTA refractaria.',
            ],
            [
                'email' => 'dra.escalante@medicita.com',
                'name' => 'Dra. Sofía Escalante Roca',
                'ci' => '30495870',
                'telefono' => '73456782',
                'especialidad_id' => $espGin->id,
                'nro_registro' => 'COL-MED-66481',
                'bio' => 'Ginecóloga y Obstetra especializada en control prenatal de alto riesgo y patología cervical.',
            ],
            [
                'email' => 'dr.morales@medicita.com',
                'name' => 'Dr. Mauricio Morales Banzer',
                'ci' => '30495871',
                'telefono' => '73456783',
                'especialidad_id' => $espOdo->id,
                'nro_registro' => 'COL-MED-55910',
                'bio' => 'Odontólogo cirujano con postgrado en rehabilitación oral, estética dental y periodoncia.',
            ],
            [
                'email' => 'dra.toledo@medicita.com',
                'name' => 'Dra. Beatriz Toledo Arteaga',
                'ci' => '30495872',
                'telefono' => '73456784',
                'especialidad_id' => $espGer->id,
                'nro_registro' => 'COL-MED-44820',
                'bio' => 'Geriatra clínica enfocada en síndromes geriátricos, salud cognitiva y autonomía funcional del adulto mayor.',
            ],
        ];

        $medicosModels = [];
        foreach ($medicosData as $m) {
            $userMed = User::firstOrCreate(
                ['email' => $m['email']],
                [
                    'name' => $m['name'],
                    'rol_id' => $rolMed->id,
                    'ci' => $m['ci'],
                    'telefono' => $m['telefono'],
                    'password' => Hash::make('Medico123!'),
                    'estado' => 'activo',
                ]
            );

            $medico = Medico::firstOrCreate(
                ['user_id' => $userMed->id],
                [
                    'especialidad_id' => $m['especialidad_id'],
                    'nro_registro_medico' => $m['nro_registro'],
                    'telefono_contacto' => $m['telefono'],
                    'biografia' => $m['bio'],
                    'estado' => 'activo',
                ]
            );

            // Horarios de Lunes (1) a Viernes (5)
            for ($dia = 1; $dia <= 5; $dia++) {
                HorarioAtencion::firstOrCreate(
                    [
                        'medico_id' => $medico->id,
                        'dia_semana' => $dia,
                    ],
                    [
                        'hora_inicio' => '08:00',
                        'hora_fin' => '16:00',
                        'duracion_slot_minutos' => 15,
                        'descanso_inicio' => '12:00',
                        'descanso_fin' => '13:00',
                        'activo' => true,
                    ]
                );
            }

            $medicosModels[$m['email']] = $medico;
        }

        // 4. Catálogo de Pacientes Realistas
        $pacientesData = [
            [
                'email' => 'paciente.demo@medicita.com',
                'ci' => '40596877',
                'nombres' => 'Juan Pablo',
                'apellidos' => 'Rojas Silva',
                'fecha_nac' => '1990-05-15',
                'genero' => 'Masculino',
                'grupo' => 'O+',
                'telefono' => '74567890',
                'direccion' => 'Av. San Martín #450, Equipetrol, Santa Cruz',
                'contacto_nombre' => 'Ana Silva (Madre)',
                'contacto_tel' => '75678901',
                'expediente' => 'HC-SCZ-00101',
                'antecedentes' => 'Hipertensión arterial esencial estadio I en control farmacológico. Dislipidemia mixta familiar.',
                'medicacion' => 'Losartán 50mg VO c/24h matutino, Atorvastatina 20mg VO c/noche.',
            ],
            [
                'email' => 'lucia.fernandez@gmail.com',
                'ci' => '60891234',
                'nombres' => 'Lucía',
                'apellidos' => 'Fernández Paredes',
                'fecha_nac' => '1996-08-22',
                'genero' => 'Femenino',
                'grupo' => 'A+',
                'telefono' => '71234560',
                'direccion' => 'Calle 7 Oeste #120, Barrio Sirari, Santa Cruz',
                'contacto_nombre' => 'Carlos Fernández (Hermano)',
                'contacto_tel' => '71234569',
                'expediente' => 'HC-SCZ-00102',
                'antecedentes' => 'ALERGIA SEVERA A PENICILINAS (urticaria y broncoespasmo). Rinitis alérgica estacional.',
                'medicacion' => 'Desloratadina 5mg VO PRN ante crisis rínica.',
            ],
            [
                'email' => 'mateo.villagomez@gmail.com',
                'ci' => '71239845',
                'nombres' => 'Mateo',
                'apellidos' => 'Villagómez Hurtado',
                'fecha_nac' => '2018-03-10',
                'genero' => 'Masculino',
                'grupo' => 'O+',
                'telefono' => '72345671',
                'direccion' => 'Av. Santos Dumont, Calle Porvenir #89, Santa Cruz',
                'contacto_nombre' => 'Patricia Hurtado (Madre)',
                'contacto_tel' => '72345671',
                'expediente' => 'HC-SCZ-00103',
                'antecedentes' => 'Asma bronquial infantil moderada. Esquema PAI al día.',
                'medicacion' => 'Salbutamol inhalador 100mcg (2 disparos con aerocámara en crisis).',
            ],
            [
                'email' => 'camila.antelo@gmail.com',
                'ci' => '52341980',
                'nombres' => 'Camila',
                'apellidos' => 'Antelo Justiniano',
                'fecha_nac' => '1982-11-04',
                'genero' => 'Femenino',
                'grupo' => 'B+',
                'telefono' => '73456782',
                'direccion' => 'Radial 19, Condominio Las Palmas Torre B #402, Santa Cruz',
                'contacto_nombre' => 'Esteban Suárez (Esposo)',
                'contacto_tel' => '73456785',
                'expediente' => 'HC-SCZ-00104',
                'antecedentes' => 'Hipotiroidismo primario diagnosticado hace 4 años. Apendicectomía a los 18 años.',
                'medicacion' => 'Levotiroxina sódica 75mcg en ayunas 30 min antes del desayuno.',
            ],
            [
                'email' => 'jorge.mercado@gmail.com',
                'ci' => '38901234',
                'nombres' => 'Jorge',
                'apellidos' => 'Mercado Saavedra',
                'fecha_nac' => '1966-02-18',
                'genero' => 'Masculino',
                'grupo' => 'A-',
                'telefono' => '74567893',
                'direccion' => 'Calle Ballivián #340, Casco Viejo, Santa Cruz',
                'contacto_nombre' => 'Sonia Rivero (Esposa)',
                'contacto_tel' => '74567895',
                'expediente' => 'HC-SCZ-00105',
                'antecedentes' => 'Diabetes Mellitus tipo 2 diagnosticada en 2019. Esteatosis hepática no alcohólica grado I.',
                'medicacion' => 'Metformina 850mg c/12h con las comidas principales.',
            ],
            [
                'email' => 'carmen.balcazar@gmail.com',
                'ci' => '29871230',
                'nombres' => 'Carmen Rosa',
                'apellidos' => 'Balcázar Aguilera',
                'fecha_nac' => '1956-07-29',
                'genero' => 'Femenino',
                'grupo' => 'O+',
                'telefono' => '75678904',
                'direccion' => 'Villa 1ro de Mayo, Calle 3 Este #56, Santa Cruz',
                'contacto_nombre' => 'Mariela Rojas (Hija)',
                'contacto_tel' => '75678906',
                'expediente' => 'HC-SCZ-00106',
                'antecedentes' => 'Gonartrosis bilateral grado II-III. Osteopenia lumbar confirmada por densitometría.',
                'medicacion' => 'Glucosamina + Condroitina 1 sobre/día, Carbonato de Calcio + Vitamina D3.',
            ],
            [
                'email' => 'rodrigo.mendez@gmail.com',
                'ci' => '81290345',
                'nombres' => 'Rodrigo',
                'apellidos' => 'Méndez Pinto',
                'fecha_nac' => '2001-09-14',
                'genero' => 'Masculino',
                'grupo' => 'AB+',
                'telefono' => '76789015',
                'direccion' => 'Av. Beni 5to Anillo, Condominio El Bosque #12',
                'contacto_nombre' => 'Claudia Pinto (Madre)',
                'contacto_tel' => '76789018',
                'expediente' => 'HC-SCZ-00107',
                'antecedentes' => 'Sin antecedentes crónicos ni patológicos de relevancia. Deportista de triatlón.',
                'medicacion' => 'Niega consumo habitual de fármacos.',
            ],
            [
                'email' => 'elena.justiniano@gmail.com',
                'ci' => '47891234',
                'nombres' => 'Elena',
                'apellidos' => 'Justiniano Ribera',
                'fecha_nac' => '1993-12-05',
                'genero' => 'Femenino',
                'grupo' => 'O+',
                'telefono' => '77890126',
                'direccion' => 'Doble Vía La Guardia Km 6, Urbanización El Trapiche',
                'contacto_nombre' => 'Mauricio Salvatierra (Pareja)',
                'contacto_tel' => '77890129',
                'expediente' => 'HC-SCZ-00108',
                'antecedentes' => 'G1P0, primigesta con embarazo normoevolutivo de 11 semanas por FUM confiable.',
                'medicacion' => 'Ácido Fólico 1mg/día VO, Sulfato Ferroso 200mg VO.',
            ],
        ];

        $pacientesModels = [];
        foreach ($pacientesData as $p) {
            $userPac = User::where('ci', $p['ci'])->orWhere('email', $p['email'])->first();
            if (! $userPac) {
                $userPac = User::create([
                    'email' => $p['email'],
                    'name' => "{$p['nombres']} {$p['apellidos']}",
                    'rol_id' => $rolPac->id,
                    'ci' => $p['ci'],
                    'telefono' => $p['telefono'],
                    'password' => Hash::make('Paciente123!'),
                    'estado' => 'activo',
                ]);
            }

            $paciente = Paciente::firstOrCreate(
                ['ci' => $p['ci']],
                [
                    'user_id' => $userPac->id,
                    'nombres' => $p['nombres'],
                    'apellidos' => $p['apellidos'],
                    'fecha_nacimiento' => $p['fecha_nac'],
                    'genero' => $p['genero'],
                    'grupo_sanguineo' => $p['grupo'],
                    'telefono' => $p['telefono'],
                    'email' => $p['email'],
                    'direccion' => $p['direccion'],
                    'contacto_emergencia_nombre' => $p['contacto_nombre'],
                    'contacto_emergencia_telefono' => $p['contacto_tel'],
                    'activo' => true,
                ]
            );

            HistoriaClinica::firstOrCreate(
                ['paciente_id' => $paciente->id],
                [
                    'nro_expediente' => $p['expediente'],
                    'antecedentes' => $p['antecedentes'],
                    'medicacion_habitual' => $p['medicacion'],
                    'version' => 1,
                    'fecha_apertura' => Carbon::now()->subMonths(6)->toDateString(),
                ]
            );

            $pacientesModels[$p['email']] = $paciente;
            if ($p['email'] === 'paciente.demo@medicita.com') {
                $pacientesModels['paciente@medicita.com'] = $paciente;
            }
        }

        // Helper para asegurar la existencia de un slot específico
        $obtenerOCrearSlot = function (Medico $medico, string $fechaStr, string $horaInicioStr) {
            $horaFinStr = Carbon::createFromFormat('H:i', $horaInicioStr)->addMinutes(15)->format('H:i');
            $horario = HorarioAtencion::where('medico_id', $medico->id)->first();

            return Slot::firstOrCreate(
                [
                    'medico_id' => $medico->id,
                    'fecha' => $fechaStr,
                    'hora_inicio' => $horaInicioStr,
                ],
                [
                    'horario_id' => $horario?->id,
                    'hora_fin' => $horaFinStr,
                    'estado' => 'disponible',
                ]
            );
        };

        // 5. Generación de Slots generales optimizada en lote
        $hoy = Carbon::today();
        $fechasGeneracion = [
            $hoy->copy()->subDays(4)->toDateString(),
            $hoy->copy()->subDays(3)->toDateString(),
            $hoy->copy()->subDays(2)->toDateString(),
            $hoy->copy()->subDays(1)->toDateString(),
            $hoy->copy()->toDateString(),
            $hoy->copy()->addDays(1)->toDateString(),
            $hoy->copy()->addDays(2)->toDateString(),
            $hoy->copy()->addDays(3)->toDateString(),
            $hoy->copy()->addDays(4)->toDateString(),
        ];

        $horasSlots = ['08:00', '08:15', '08:30', '08:45', '09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00', '11:15', '11:30', '11:45', '13:00', '13:15', '13:30', '13:45', '14:00', '14:15', '14:30', '14:45', '15:00', '15:15', '15:30', '15:45'];

        $medicoIds = array_map(fn ($m) => $m->id, $medicosModels);
        $horariosMap = HorarioAtencion::whereIn('medico_id', $medicoIds)->get()->groupBy('medico_id');
        $existingKeys = [];
        foreach (Slot::whereIn('medico_id', $medicoIds)->whereIn('fecha', $fechasGeneracion)->get(['medico_id', 'fecha', 'hora_inicio']) as $es) {
            $fecStr = is_string($es->fecha) ? $es->fecha : $es->fecha->toDateString();
            $existingKeys["{$es->medico_id}_{$fecStr}_{$es->hora_inicio}"] = true;
        }

        $nuevosSlots = [];
        $nowTimestamp = now();
        foreach ($medicosModels as $medico) {
            $horario = $horariosMap[$medico->id]->first() ?? null;
            foreach ($fechasGeneracion as $fechaStr) {
                foreach ($horasSlots as $horaStr) {
                    $key = "{$medico->id}_{$fechaStr}_{$horaStr}";
                    if (! isset($existingKeys[$key])) {
                        $horaFinStr = Carbon::createFromFormat('H:i', $horaStr)->addMinutes(15)->format('H:i');
                        $nuevosSlots[] = [
                            'medico_id' => $medico->id,
                            'horario_id' => $horario?->id,
                            'fecha' => $fechaStr,
                            'hora_inicio' => $horaStr,
                            'hora_fin' => $horaFinStr,
                            'estado' => 'disponible',
                            'created_at' => $nowTimestamp,
                            'updated_at' => $nowTimestamp,
                        ];
                        $existingKeys[$key] = true;
                    }
                }
            }
        }

        if (! empty($nuevosSlots)) {
            foreach (array_chunk($nuevosSlots, 500) as $chunk) {
                Slot::insertOrIgnore($chunk);
            }
        }

        $medPerez = $medicosModels['dr.perez@medicita.com'];
        $medGomez = $medicosModels['dra.gomez@medicita.com'];
        $medVargas = $medicosModels['dr.vargas@medicita.com'];
        $medEscalante = $medicosModels['dra.escalante@medicita.com'];

        $pacRojas = $pacientesModels['paciente@medicita.com'];
        $pacLucia = $pacientesModels['lucia.fernandez@gmail.com'];
        $pacMateo = $pacientesModels['mateo.villagomez@gmail.com'];
        $pacCamila = $pacientesModels['camila.antelo@gmail.com'];
        $pacJorge = $pacientesModels['jorge.mercado@gmail.com'];
        $pacCarmen = $pacientesModels['carmen.balcazar@gmail.com'];
        $pacRodrigo = $pacientesModels['rodrigo.mendez@gmail.com'];
        $pacElena = $pacientesModels['elena.justiniano@gmail.com'];

        // 6. Citas Médicas, Consultas Clínicas (CU14) y Derivaciones

        // CITA 1: Atendida / Completada hace 2 días (Juan Pablo Rojas con Dr. Pérez)
        $fechaCita1 = $hoy->copy()->subDays(2)->toDateString();
        $slot1 = $obtenerOCrearSlot($medPerez, $fechaCita1, '09:00');
        $slot1->update(['estado' => 'reservado']);

        $cita1 = Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261105-0001'],
            [
                'paciente_id' => $pacRojas->id,
                'medico_id' => $medPerez->id,
                'slot_id' => $slot1->id,
                'fecha' => $fechaCita1,
                'hora' => '09:00',
                'estado' => 'completada',
                'motivo_consulta' => 'Control de presión arterial elevada matutina recurrente con cefalea occipital pulsátil.',
                'hora_checkin' => Carbon::parse($fechaCita1.' 08:45:00'),
                'hora_llegada' => Carbon::parse($fechaCita1.' 08:45:00'),
                'hora_inicio_atencion' => Carbon::parse($fechaCita1.' 09:03:00'),
                'hora_fin_atencion' => Carbon::parse($fechaCita1.' 09:28:00'),
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        HistorialCita::firstOrCreate(
            ['cita_id' => $cita1->id, 'operacion' => 'CONCLUIR_CONSULTA'],
            [
                'user_id' => $medPerez->user_id,
                'estado_anterior' => 'en_atencion',
                'estado_nuevo' => 'completada',
                'fecha_anterior' => $fechaCita1.' 09:00:00',
                'fecha_nueva' => $fechaCita1.' 09:00:00',
                'slot_anterior_id' => $slot1->id,
                'slot_nuevo_id' => $slot1->id,
                'medico_anterior_id' => $medPerez->id,
                'medico_nuevo_id' => $medPerez->id,
                'motivo' => 'Consulta concluida satisfactoriamente con emisión de receta y orden de laboratorio.',
                'operacion_id' => (string) Str::uuid(),
                'aceptacion_paciente' => true,
            ]
        );

        $historiaRojas = HistoriaClinica::where('paciente_id', $pacRojas->id)->first();
        $atencion1 = AtencionMedica::firstOrCreate(
            ['cita_id' => $cita1->id],
            [
                'historia_clinica_id' => $historiaRojas->id,
                'user_id' => $medPerez->user_id,
                'motivo_consulta' => 'Cefalea pulsátil de intensidad 6/10 en región occipital, cifras tensionales elevadas en domicilio.',
                'hallazgos' => 'PA: 145/95 mmHg en brazo derecho, 142/92 mmHg en brazo izquierdo. FC: 78 lpm, afebril (36.5 °C). SatO2: 98%. Corazón rítmico, R1 y R2 normofonéticos sin soplos. Campos pulmonares libres de estertores.',
                'diagnostico' => 'Hipertensión arterial esencial estadio I no controlada (CIE-10: I10). Dislipidemia aterogénica moderada (CIE-10: E78.2).',
                'tratamiento' => "1. Ajustar Losartán a 100mg VO cada mañana en ayunas.\n2. Iniciar Amlodipino 5mg VO cada tarde (18:00).\n3. Mantener Atorvastatina 20mg nocturna.",
                'indicaciones' => 'Dieta estricta hiposódica (<2g sal/día). Caminata aeróbica 40 min al día. Registro ambulatorio diario de presión. Se emite orden de laboratorio completa y derivación a Cardiología para ecocardiograma.',
                'antecedentes' => $historiaRojas->antecedentes,
                'medicacion_habitual' => $historiaRojas->medicacion_habitual,
                'operacion_id' => (string) Str::uuid(),
                'es_correccion' => false,
            ]
        );

        Derivacion::firstOrCreate(
            ['atencion_medica_id' => $atencion1->id],
            [
                'especialidad_id' => $espCard->id,
                'user_id' => $medPerez->user_id,
                'motivo' => 'Evaluación de daño a órgano blanco por HTA de difícil control. Requiere Ecocardiograma Transtorácico y Holter tensional (MAPA 24h).',
                'indicaciones' => 'Adjuntar último perfil lipídico y registro de cifras tensionales ambulatorias.',
                'estado' => 'emitida',
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        // Orden de Laboratorio 1: Finalizada / Publicada con Resultados (CU15)
        $orden1 = OrdenLaboratorio::firstOrCreate(
            ['codigo_orden' => 'ORD-2026-0001'],
            [
                'paciente_id' => $pacRojas->id,
                'medico_id' => $medPerez->id,
                'atencion_medica_id' => $atencion1->id,
                'cita_id' => $cita1->id,
                'estado' => 'finalizada',
                'observaciones' => 'Control de perfil lipídico completo, glicemia y función renal post-ajuste antihipertensivo.',
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        $exHem = ExamenLaboratorio::where('codigo', 'HEM-001')->first();
        $exLip = ExamenLaboratorio::where('codigo', 'LIP-001')->first();
        $exGlu = ExamenLaboratorio::where('codigo', 'GLU-001')->first();

        if ($exHem && $exLip && $exGlu) {
            OrdenExamen::firstOrCreate(['orden_laboratorio_id' => $orden1->id, 'examen_laboratorio_id' => $exHem->id], ['estado' => 'informado']);
            OrdenExamen::firstOrCreate(['orden_laboratorio_id' => $orden1->id, 'examen_laboratorio_id' => $exLip->id], ['estado' => 'informado']);
            OrdenExamen::firstOrCreate(['orden_laboratorio_id' => $orden1->id, 'examen_laboratorio_id' => $exGlu->id], ['estado' => 'informado']);

            $pdfRelativePath = 'laboratorio/ORD-2026-0001_resultado.pdf';
            Storage::disk('local')->put($pdfRelativePath, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000108 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n187\n%%EOF\n");

            $resultado1 = ResultadoLaboratorio::firstOrCreate(
                ['codigo_resultado' => 'RES-2026-0001'],
                [
                    'orden_laboratorio_id' => $orden1->id,
                    'user_id' => $bioquimico1->id,
                    'version' => 1,
                    'estado' => 'publicado',
                    'fecha_validacion' => Carbon::now()->subDay()->setHour(14)->setMinute(30),
                    'validado_por_user_id' => $bioquimico1->id,
                    'fecha_publicacion' => Carbon::now()->subDay()->setHour(15)->setMinute(0),
                    'publicado_por_user_id' => $bioquimico1->id,
                    'archivo_pdf_path' => $pdfRelativePath,
                    'observaciones' => 'Muestra venosa procesada sin hemólisis. Glucemia en rango normal. Perfil lipídico muestra elevación moderada de colesterol total y LDL.',
                    'operacion_id' => (string) Str::uuid(),
                ]
            );

            $detallesData = [
                ['examen_id' => $exGlu->id, 'parametro' => 'Glucosa Basal en Suero', 'valor' => '94.0', 'unidad' => 'mg/dL', 'rango' => '70 - 100', 'interp' => 'Normal'],
                ['examen_id' => $exLip->id, 'parametro' => 'Colesterol Total', 'valor' => '228.0', 'unidad' => 'mg/dL', 'rango' => '< 200', 'interp' => 'Alto'],
                ['examen_id' => $exLip->id, 'parametro' => 'Triglicéridos', 'valor' => '185.0', 'unidad' => 'mg/dL', 'rango' => '< 150', 'interp' => 'Alto'],
                ['examen_id' => $exLip->id, 'parametro' => 'Colesterol HDL (Protector)', 'valor' => '42.0', 'unidad' => 'mg/dL', 'rango' => '> 40', 'interp' => 'Normal'],
                ['examen_id' => $exLip->id, 'parametro' => 'Colesterol LDL (Aterogénico)', 'valor' => '149.0', 'unidad' => 'mg/dL', 'rango' => '< 100', 'interp' => 'Alto'],
                ['examen_id' => $exHem->id, 'parametro' => 'Hemoglobina', 'valor' => '15.2', 'unidad' => 'g/dL', 'rango' => '13.5 - 17.5', 'interp' => 'Normal'],
                ['examen_id' => $exHem->id, 'parametro' => 'Hematocrito', 'valor' => '45.1', 'unidad' => '%', 'rango' => '41 - 50', 'interp' => 'Normal'],
                ['examen_id' => $exHem->id, 'parametro' => 'Leucocitos Totales', 'valor' => '7,100', 'unidad' => '/mm³', 'rango' => '4,500 - 10,000', 'interp' => 'Normal'],
                ['examen_id' => $exHem->id, 'parametro' => 'Plaquetas', 'valor' => '260,000', 'unidad' => '/mm³', 'rango' => '150,000 - 400,000', 'interp' => 'Normal'],
            ];

            foreach ($detallesData as $det) {
                DetalleResultado::firstOrCreate(
                    [
                        'resultado_laboratorio_id' => $resultado1->id,
                        'examen_laboratorio_id' => $det['examen_id'],
                        'parametro' => $det['parametro'],
                    ],
                    [
                        'valor' => $det['valor'],
                        'unidad_medida' => $det['unidad'],
                        'rango_referencia' => $det['rango'],
                        'interpretacion' => $det['interp'],
                    ]
                );
            }

            try {
                $resultado1->load(['orden.paciente', 'orden.medico.user', 'detalles.examen', 'validadoPor', 'publicadoPor']);
                $pdfContent = app(LaboratorioPdfService::class)->generar($resultado1);
                Storage::disk('local')->put($pdfRelativePath, $pdfContent);
                $resultado1->updateQuietly([
                    'archivo_pdf_contenido' => base64_encode($pdfContent),
                ]);
            } catch (\Throwable) {
                // Continuar si ocurre alguna inconsistencia de entorno
            }
        }

        // CITA 2: Atendida hace 3 días (Jorge Mercado con Dr. Pérez)
        $fechaCita2 = $hoy->copy()->subDays(3)->toDateString();
        $slot2 = $obtenerOCrearSlot($medPerez, $fechaCita2, '10:15');
        $slot2->update(['estado' => 'reservado']);

        $cita2 = Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261104-0002'],
            [
                'paciente_id' => $pacJorge->id,
                'medico_id' => $medPerez->id,
                'slot_id' => $slot2->id,
                'fecha' => $fechaCita2,
                'hora' => '10:15',
                'estado' => 'completada',
                'motivo_consulta' => 'Control de Diabetes Mellitus tipo 2, fatiga vespertina y sed intensa.',
                'hora_checkin' => Carbon::parse($fechaCita2.' 10:00:00'),
                'hora_llegada' => Carbon::parse($fechaCita2.' 10:00:00'),
                'hora_inicio_atencion' => Carbon::parse($fechaCita2.' 10:16:00'),
                'hora_fin_atencion' => Carbon::parse($fechaCita2.' 10:45:00'),
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        $historiaJorge = HistoriaClinica::where('paciente_id', $pacJorge->id)->first();
        $atencion2 = AtencionMedica::firstOrCreate(
            ['cita_id' => $cita2->id],
            [
                'historia_clinica_id' => $historiaJorge->id,
                'user_id' => $medPerez->user_id,
                'motivo_consulta' => 'Polidipsia y astenia moderada de 3 semanas. Glucemias caseras oscilan en 160-180 mg/dL.',
                'hallazgos' => 'PA: 130/80 mmHg, Peso: 84.5 kg, Talla: 1.69 m, IMC: 29.6 kg/m². Abdomen globoso a expensas de panículo adiposo, no doloroso. Pies: reflejos presentes, sin úlceras.',
                'diagnostico' => 'Diabetes mellitus tipo 2 descompensada levemente (CIE-10: E11.65). Sobrepeso grado II.',
                'tratamiento' => "1. Optimizar Metformina 850mg VO cada 12 horas con las comidas principales.\n2. Iniciar Empagliflozina 10mg VO cada mañana.",
                'indicaciones' => 'Plan de alimentación estricto con nutricionista. Caminata 30 min diarios. Se emite orden de laboratorio urgente.',
                'antecedentes' => $historiaJorge->antecedentes,
                'medicacion_habitual' => $historiaJorge->medicacion_habitual,
                'operacion_id' => (string) Str::uuid(),
                'es_correccion' => false,
            ]
        );

        // Orden de Laboratorio 2: En Proceso / Validada
        $orden2 = OrdenLaboratorio::firstOrCreate(
            ['codigo_orden' => 'ORD-2026-0002'],
            [
                'paciente_id' => $pacJorge->id,
                'medico_id' => $medPerez->id,
                'atencion_medica_id' => $atencion2->id,
                'cita_id' => $cita2->id,
                'estado' => 'en_proceso',
                'observaciones' => 'Perfil metabólico: Glucosa basal, HbA1c y Creatinina sérica.',
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        $exCre = ExamenLaboratorio::where('codigo', 'CRE-001')->first();
        $exHba1c = ExamenLaboratorio::where('codigo', 'HBA1C-001')->first();

        if ($exGlu && $exCre) {
            OrdenExamen::firstOrCreate(['orden_laboratorio_id' => $orden2->id, 'examen_laboratorio_id' => $exGlu->id], ['estado' => 'informado']);
            OrdenExamen::firstOrCreate(['orden_laboratorio_id' => $orden2->id, 'examen_laboratorio_id' => $exCre->id], ['estado' => 'informado']);
            if ($exHba1c) {
                OrdenExamen::firstOrCreate(['orden_laboratorio_id' => $orden2->id, 'examen_laboratorio_id' => $exHba1c->id], ['estado' => 'en_proceso']);
            }

            $resultado2 = ResultadoLaboratorio::firstOrCreate(
                ['codigo_resultado' => 'RES-2026-0002'],
                [
                    'orden_laboratorio_id' => $orden2->id,
                    'user_id' => $bioquimico1->id,
                    'version' => 1,
                    'estado' => 'validado',
                    'fecha_validacion' => Carbon::now()->subHours(5),
                    'validado_por_user_id' => $bioquimico1->id,
                    'observaciones' => 'Glucemia marcadamente elevada en ayunas. Función renal preservada con creatinina normal. Pendiente el reporte de hemoglobina glicosilada.',
                    'operacion_id' => (string) Str::uuid(),
                ]
            );

            DetalleResultado::firstOrCreate(
                ['resultado_laboratorio_id' => $resultado2->id, 'examen_laboratorio_id' => $exGlu->id, 'parametro' => 'Glucosa Basal'],
                ['valor' => '164.0', 'unidad_medida' => 'mg/dL', 'rango_referencia' => '70 - 100', 'interpretacion' => 'Alto']
            );
            DetalleResultado::firstOrCreate(
                ['resultado_laboratorio_id' => $resultado2->id, 'examen_laboratorio_id' => $exCre->id, 'parametro' => 'Creatinina Sérica'],
                ['valor' => '1.02', 'unidad_medida' => 'mg/dL', 'rango_referencia' => '0.7 - 1.3', 'interpretacion' => 'Normal']
            );
        }

        // CITA 3: Atendida ayer (Lucía Fernández con Dra. Gómez)
        $fechaCita3 = $hoy->copy()->subDays(1)->toDateString();
        $slot3 = $obtenerOCrearSlot($medGomez, $fechaCita3, '11:00');
        $slot3->update(['estado' => 'reservado']);

        $cita3 = Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261106-0003'],
            [
                'paciente_id' => $pacLucia->id,
                'medico_id' => $medGomez->id,
                'slot_id' => $slot3->id,
                'fecha' => $fechaCita3,
                'hora' => '11:00',
                'estado' => 'completada',
                'motivo_consulta' => 'Disuria intensa, polaquiuria y dolor lumbar leve de 48h.',
                'hora_checkin' => Carbon::parse($fechaCita3.' 10:50:00'),
                'hora_llegada' => Carbon::parse($fechaCita3.' 10:50:00'),
                'hora_inicio_atencion' => Carbon::parse($fechaCita3.' 11:02:00'),
                'hora_fin_atencion' => Carbon::parse($fechaCita3.' 11:25:00'),
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        $historiaLucia = HistoriaClinica::where('paciente_id', $pacLucia->id)->first();
        $atencion3 = AtencionMedica::firstOrCreate(
            ['cita_id' => $cita3->id],
            [
                'historia_clinica_id' => $historiaLucia->id,
                'user_id' => $medGomez->user_id,
                'motivo_consulta' => 'Molestias urinarias agudas con ardor miccional y sensación de vaciado incompleto.',
                'hallazgos' => 'Temp: 37.1 °C, PA: 115/75 mmHg. PPL (Puño percusión lumbar) bilateral negativa. Abdomen blando, hipogástrico sensible a la palpación profunda.',
                'diagnostico' => 'Infección aguda del tracto urinario bajo / Cistitis aguda (CIE-10: N30.0). Alergia a Penicilinas.',
                'tratamiento' => "Fosfomicina trometamol 3g polvo para solución oral, dosis única nocturna tras vaciar la vejiga.\nNitrofurantoína 100mg VO cada 8 horas por 5 días.",
                'indicaciones' => 'Abundante hidratación oral (2.5 litros de agua/día). Se solicita EGO y Urocultivo para antibiograma.',
                'antecedentes' => $historiaLucia->antecedentes,
                'medicacion_habitual' => $historiaLucia->medicacion_habitual,
                'operacion_id' => (string) Str::uuid(),
                'es_correccion' => false,
            ]
        );

        // Orden de Laboratorio 3: Emitida / Pendiente de Procesar
        $orden3 = OrdenLaboratorio::firstOrCreate(
            ['codigo_orden' => 'ORD-2026-0003'],
            [
                'paciente_id' => $pacLucia->id,
                'medico_id' => $medGomez->id,
                'atencion_medica_id' => $atencion3->id,
                'cita_id' => $cita3->id,
                'estado' => 'emitida',
                'observaciones' => 'Urgente: Examen general de orina y urocultivo con recuento de colonias y antibiograma.',
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        $exUri = ExamenLaboratorio::where('codigo', 'URI-001')->first();
        $exUroc = ExamenLaboratorio::where('codigo', 'UROC-001')->first();
        if ($exUri) {
            OrdenExamen::firstOrCreate(['orden_laboratorio_id' => $orden3->id, 'examen_laboratorio_id' => $exUri->id], ['estado' => 'pendiente']);
        }
        if ($exUroc) {
            OrdenExamen::firstOrCreate(['orden_laboratorio_id' => $orden3->id, 'examen_laboratorio_id' => $exUroc->id], ['estado' => 'pendiente']);
        }

        // CITA 4: Hoy en estado "en_espera" (Check-in realizado en recepción)
        $fechaHoy = $hoy->toDateString();
        $slotHoy1 = $obtenerOCrearSlot($medPerez, $fechaHoy, '09:30');
        $slotHoy1->update(['estado' => 'reservado']);

        Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261107-0004'],
            [
                'paciente_id' => $pacCamila->id,
                'medico_id' => $medPerez->id,
                'slot_id' => $slotHoy1->id,
                'fecha' => $fechaHoy,
                'hora' => '09:30',
                'estado' => 'en_espera',
                'motivo_consulta' => 'Control endocrinológico: caída de cabello, piel seca y cansancio diurno.',
                'hora_checkin' => Carbon::now()->subMinutes(15),
                'hora_llegada' => Carbon::now()->subMinutes(15),
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        // CITA 5: Hoy en estado "confirmada"
        $slotHoy2 = $obtenerOCrearSlot($medVargas, $fechaHoy, '11:15');
        $slotHoy2->update(['estado' => 'reservado']);

        Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261107-0005'],
            [
                'paciente_id' => $pacRodrigo->id,
                'medico_id' => $medVargas->id,
                'slot_id' => $slotHoy2->id,
                'fecha' => $fechaHoy,
                'hora' => '11:15',
                'estado' => 'confirmada',
                'motivo_consulta' => 'Evaluación cardiológica deportiva de alta intensidad: episodios de palpitaciones.',
                'hora_confirmacion' => Carbon::now()->subHours(2),
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        // CITA 6: Hoy en estado "programada"
        $slotHoy3 = $obtenerOCrearSlot($medEscalante, $fechaHoy, '15:30');
        $slotHoy3->update(['estado' => 'reservado']);

        Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261107-0006'],
            [
                'paciente_id' => $pacElena->id,
                'medico_id' => $medEscalante->id,
                'slot_id' => $slotHoy3->id,
                'fecha' => $fechaHoy,
                'hora' => '15:30',
                'estado' => 'programada',
                'motivo_consulta' => 'Primera ecografía obstétrica de translucencia nucal y control prenatal.',
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        // CITA 7: Cancelada (liberó su slot para demostrar CU18 Lista de Espera)
        $fechaAyer = $hoy->copy()->subDays(1)->toDateString();
        $slotCanc = $obtenerOCrearSlot($medVargas, $fechaAyer, '14:00');
        $slotCanc->update(['estado' => 'disponible']);

        Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261106-0007'],
            [
                'paciente_id' => $pacCarmen->id,
                'medico_id' => $medVargas->id,
                'slot_id' => $slotCanc->id,
                'fecha' => $fechaAyer,
                'hora' => '14:00',
                'estado' => 'cancelada',
                'motivo_consulta' => 'Evaluación de palpitaciones y disnea de esfuerzo.',
                'motivo_cancelacion' => 'Paciente reporta imposibilidad de transporte por lluvia torrencial. Solicita reprogramación.',
                'cancelado_por_user_id' => $recepcionista1->id,
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        // CITA 8: Inasistencia / No-Show para KPIs de Reportes CU17
        $fechaPasada4 = $hoy->copy()->subDays(3)->toDateString();
        $slotInas = $obtenerOCrearSlot($medGomez, $fechaPasada4, '15:00');
        $slotInas->update(['estado' => 'reservado']);

        Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261104-0008'],
            [
                'paciente_id' => $pacMateo->id,
                'medico_id' => $medGomez->id,
                'slot_id' => $slotInas->id,
                'fecha' => $fechaPasada4,
                'hora' => '15:00',
                'estado' => 'inasistencia',
                'motivo_consulta' => 'Control de crecimiento y desarrollo pediátrico.',
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        // 7. Lista de Espera y Recuperación de Cupos (CU18)

        // Entrada 1 en Espera: Activa para Cardiología
        ListaEspera::firstOrCreate(
            ['paciente_id' => $pacRodrigo->id, 'especialidad_id' => $espCard->id],
            [
                'medico_id' => $medVargas->id,
                'fecha_desde' => $hoy->copy()->addDay()->toDateString(),
                'fecha_hasta' => $hoy->copy()->addDays(5)->toDateString(),
                'franja_horaria' => 'manana',
                'anticipacion_minima_horas' => 2,
                'estado' => 'activa',
                'fecha_inscripcion' => $hoy->toDateString(),
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        // Entrada 2 en Espera: Activa para Medicina General
        ListaEspera::firstOrCreate(
            ['paciente_id' => $pacCamila->id, 'especialidad_id' => $espGen->id],
            [
                'medico_id' => $medPerez->id,
                'fecha_desde' => $hoy->copy()->toDateString(),
                'fecha_hasta' => $hoy->copy()->addDays(4)->toDateString(),
                'franja_horaria' => 'cualquiera',
                'anticipacion_minima_horas' => 1,
                'estado' => 'activa',
                'fecha_inscripcion' => $hoy->toDateString(),
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        // Entrada 3 en Espera: Con Oferta Pendiente (vence en 8 min) para demostración visual de CU18
        $esperaConOferta = ListaEspera::firstOrCreate(
            ['paciente_id' => $pacCarmen->id, 'especialidad_id' => $espCard->id],
            [
                'medico_id' => $medVargas->id,
                'fecha_desde' => $hoy->toDateString(),
                'fecha_hasta' => $hoy->copy()->addDays(3)->toDateString(),
                'franja_horaria' => 'tarde',
                'anticipacion_minima_horas' => 2,
                'estado' => 'activa',
                'fecha_inscripcion' => $hoy->toDateString(),
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        $slotOferta = $obtenerOCrearSlot($medVargas, $hoy->copy()->addDays(1)->toDateString(), '14:30');
        OfertaCupo::firstOrCreate(
            ['lista_espera_id' => $esperaConOferta->id, 'slot_id' => $slotOferta->id],
            [
                'estado' => 'pendiente',
                'fecha_oferta' => Carbon::now()->subMinutes(2),
                'fecha_vencimiento' => Carbon::now()->addMinutes(8),
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        // Entrada 4 en Espera: Oferta Aceptada y Cupo Recuperado con éxito
        $esperaAceptada = ListaEspera::firstOrCreate(
            ['paciente_id' => $pacLucia->id, 'especialidad_id' => $espGen->id],
            [
                'medico_id' => $medPerez->id,
                'fecha_desde' => $hoy->copy()->subDays(2)->toDateString(),
                'fecha_hasta' => $hoy->toDateString(),
                'franja_horaria' => 'manana',
                'anticipacion_minima_horas' => 2,
                'estado' => 'atendida',
                'fecha_inscripcion' => $hoy->copy()->subDays(4)->toDateString(),
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        $slotRecuperado = $obtenerOCrearSlot($medPerez, $hoy->copy()->addDays(2)->toDateString(), '08:30');
        $slotRecuperado->update(['estado' => 'reservado']);

        $ofertaAceptada = OfertaCupo::firstOrCreate(
            ['lista_espera_id' => $esperaAceptada->id, 'slot_id' => $slotRecuperado->id],
            [
                'estado' => 'aceptada',
                'fecha_oferta' => Carbon::now()->subDays(1),
                'fecha_vencimiento' => Carbon::now()->subDays(1)->addMinutes(10),
                'respondida_at' => Carbon::now()->subDays(1)->addMinutes(3),
                'motivo_respuesta' => 'Aceptación inmediata por portal del paciente.',
                'operacion_id' => (string) Str::uuid(),
            ]
        );

        Cita::firstOrCreate(
            ['codigo_ficha' => 'FCH-20261109-0009'],
            [
                'paciente_id' => $pacLucia->id,
                'medico_id' => $medPerez->id,
                'slot_id' => $slotRecuperado->id,
                'fecha' => $hoy->copy()->addDays(2)->toDateString(),
                'hora' => '08:30',
                'estado' => 'confirmada',
                'motivo_consulta' => 'Control evolutivo de síntomas respiratorios y revisión de orina.',
                'es_cupo_recuperado' => true,
                'oferta_cupo_id' => $ofertaAceptada->id,
                'operacion_id' => (string) Str::uuid(),
                'version' => 1,
            ]
        );

        // 8. Consultas Previas de Reportes con Inteligencia Artificial (CU17)
        ReporteIa::firstOrCreate(
            ['pregunta_usuario' => '¿Cuál es la tasa de asistencia efectiva y distribución de citas por estado este mes?'],
            [
                'user_id' => $admin->id,
                'sql_generado' => 'SELECT estado, count(*) as total FROM citas GROUP BY estado LIMIT 100',
                'es_valido' => true,
                'filas_retornadas' => 5,
                'tiempo_ms' => 42,
            ]
        );

        ReporteIa::firstOrCreate(
            ['pregunta_usuario' => 'Top médicos con mayor cantidad de turnos completados y atendidos'],
            [
                'user_id' => $admin->id,
                'sql_generado' => 'SELECT m.nro_registro_medico, u.name as medico, count(c.id) as citas_atendidas FROM medicos m JOIN users u ON u.id = m.user_id JOIN citas c ON c.medico_id = m.id WHERE c.estado = \'completada\' GROUP BY m.nro_registro_medico, u.name LIMIT 100',
                'es_valido' => true,
                'filas_retornadas' => 4,
                'tiempo_ms' => 68,
            ]
        );

        ReporteIa::firstOrCreate(
            ['pregunta_usuario' => 'Total de exámenes clínicos solicitados y estado de órdenes en laboratorio'],
            [
                'user_id' => $admin->id,
                'sql_generado' => 'SELECT estado, count(*) as cantidad FROM ordenes_laboratorio GROUP BY estado LIMIT 100',
                'es_valido' => true,
                'filas_retornadas' => 3,
                'tiempo_ms' => 35,
            ]
        );

        // 9. Bitácora de Auditoría Realista (CU19)
        $bitacoras = [
            ['user' => $admin, 'accion' => 'LOGIN_EXITOSO', 'modulo' => 'P1_Seguridad', 'detalle' => ['ip' => '192.168.1.10', 'metodo' => 'Credenciales Directas']],
            ['user' => $recepcionista1, 'accion' => 'LOGIN_EXITOSO', 'modulo' => 'P1_Seguridad', 'detalle' => ['ip' => '192.168.1.25', 'turno' => 'Mañana']],
            ['user' => $recepcionista1, 'accion' => 'CHECKIN_PACIENTE', 'modulo' => 'P4_Citas', 'detalle' => ['ficha' => 'FCH-20261107-0004', 'paciente' => 'Camila Antelo', 'sala' => 'Espera 1']],
            ['user' => $medPerez->user, 'accion' => 'LOGIN_EXITOSO', 'modulo' => 'P1_Seguridad', 'detalle' => ['ip' => '192.168.1.40', 'consultorio' => 'Cons. 102']],
            ['user' => $medPerez->user, 'accion' => 'REGISTRAR_ATENCION_MEDICA', 'modulo' => 'P3_Pacientes', 'detalle' => ['expediente' => 'HC-SCZ-00101', 'diagnostico' => 'I10 HTA']],
            ['user' => $medPerez->user, 'accion' => 'EMITIR_ORDEN_LABORATORIO', 'modulo' => 'P5_Laboratorio', 'detalle' => ['codigo_orden' => 'ORD-2026-0001', 'examenes' => 3]],
            ['user' => $bioquimico1, 'accion' => 'VALIDAR_RESULTADO_LAB', 'modulo' => 'P5_Laboratorio', 'detalle' => ['codigo_resultado' => 'RES-2026-0001', 'estado' => 'validado']],
            ['user' => $bioquimico1, 'accion' => 'PUBLICAR_RESULTADO_LAB', 'modulo' => 'P5_Laboratorio', 'detalle' => ['codigo_resultado' => 'RES-2026-0001', 'firma_hash' => 'sha256-verified']],
            ['user' => $recepcionista1, 'accion' => 'INSCRIBIR_LISTA_ESPERA', 'modulo' => 'P4_Citas', 'detalle' => ['paciente' => 'Rodrigo Méndez', 'especialidad' => 'Cardiología']],
            ['user' => $admin, 'accion' => 'CONSULTA_ANALITICA_IA', 'modulo' => 'P6_Reportes', 'detalle' => ['query' => 'Tasa de asistencia', 'filas' => 5]],
        ];

        foreach ($bitacoras as $b) {
            Bitacora::firstOrCreate(
                [
                    'user_id' => $b['user']->id,
                    'accion' => $b['accion'],
                    'modulo' => $b['modulo'],
                ],
                [
                    'usuario_nombre' => $b['user']->name,
                    'rol' => $b['user']->rol?->slug ?? 'usuario',
                    'ip_address' => '192.168.1.100',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
                    'detalles' => json_encode($b['detalle']),
                    'created_at' => Carbon::now()->subMinutes(rand(10, 400)),
                ]
            );
        }

        // 10. Avisos y Notificaciones Claras para Pacientes
        Notificacion::firstOrCreate(
            ['evento_id' => 'notif:demo:lab:res:1'],
            [
                'user_id' => $pacRojas->user_id,
                'tipo' => 'laboratorio',
                'titulo' => 'Resultados de Laboratorio Disponibles',
                'mensaje' => 'Su informe de análisis clínico ORD-2026-0001 ha sido validado y publicado por Bioquímica. Puede consultarlo y descargarlo desde su portal.',
                'leido' => false,
            ]
        );

        Notificacion::firstOrCreate(
            ['evento_id' => 'notif:demo:espera:oferta:1'],
            [
                'user_id' => $pacCarmen->user_id,
                'tipo' => 'espera',
                'titulo' => '¡Cupo Disponible en Lista de Espera!',
                'mensaje' => 'Se ha liberado un turno con Dr. Fernando Vargas para mañana a las 14:30. Dispone de 10 minutos para confirmar su cupo.',
                'leido' => false,
            ]
        );
    }
}
