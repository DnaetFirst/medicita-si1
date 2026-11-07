<?php

namespace Tests\Feature;

use App\Models\P2\BloqueoAgenda;
use App\Models\P2\Especialidad;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Etapa3Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $recepcionista;

    protected Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@medicita.com')->first();
        $this->recepcionista = User::where('email', 'recepcion@medicita.com')->first();
        $this->medico = Medico::with('user')->first();
    }

    /**
     * CU4: Listado general de médicos accesible para Admin y Recepcionista.
     */
    public function test_can_view_doctors_list(): void
    {
        $response = $this->actingAs($this->admin)->get(route('medicos.index'));
        $response->assertStatus(200);
        $response->assertSee('Gestión de Médicos (CU4)');
        $response->assertSee($this->medico->user->name);

        $responseRecep = $this->actingAs($this->recepcionista)->get(route('medicos.index'));
        $responseRecep->assertStatus(200);
    }

    /**
     * CU4: Creación de un nuevo médico con cuenta de usuario y especialidad asociada.
     */
    public function test_can_create_a_new_doctor(): void
    {
        $especialidad = Especialidad::first();

        $data = [
            'name' => 'Dr. Fernando Morales',
            'email' => 'fernando.morales@medicita.com',
            'password' => 'Medico123!',
            'password_confirmation' => 'Medico123!',
            'ci' => '7891234',
            'telefono' => '78945612',
            'especialidad_id' => $especialidad->id,
            'nro_registro_medico' => 'COL-MED-9988',
            'biografia' => 'Especialista con 10 años de experiencia clínica.',
            'estado' => 'activo',
            'dias' => [
                1 => [
                    'activo' => '1',
                    'hora_inicio' => '08:00',
                    'hora_fin' => '16:00',
                    'descanso_inicio' => '12:00',
                    'descanso_fin' => '13:00',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('medicos.store'), $data);

        $response->assertRedirect(route('medicos.index'));
        $this->assertDatabaseHas('users', ['email' => 'fernando.morales@medicita.com']);
        $this->assertDatabaseHas('medicos', ['nro_registro_medico' => 'COL-MED-9988']);
        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'REGISTRAR_MEDICO',
            'modulo' => 'P2_Medicos',
        ]);
    }

    /**
     * CU4: Ver perfil completo y estado de médico.
     */
    public function test_can_view_doctor_profile(): void
    {
        $response = $this->actingAs($this->admin)->get(route('medicos.show', $this->medico));

        $response->assertStatus(200);
        $response->assertSee($this->medico->user->name);
        $response->assertSee($this->medico->nro_registro_medico);
    }

    /**
     * CU4: Toggle del estado activo/inactivo del médico.
     */
    public function test_can_toggle_doctor_status(): void
    {
        $this->assertEquals('activo', $this->medico->estado);

        $response = $this->actingAs($this->admin)->patch(route('medicos.toggle', $this->medico));
        $response->assertRedirect();

        $this->medico->refresh();
        $this->assertEquals('inactivo', $this->medico->estado);
    }

    /**
     * CU5: Configurar horario semanal del médico con descanso estricto de 1 hora.
     */
    public function test_can_update_doctor_weekly_schedule(): void
    {
        $payload = [
            'dias' => [
                1 => [ // Lunes
                    'activo' => '1',
                    'hora_inicio' => '08:00',
                    'hora_fin' => '14:00',
                    'descanso_inicio' => '12:00',
                    'descanso_fin' => '13:00',
                ],
                2 => [ // Martes
                    'activo' => '1',
                    'hora_inicio' => '14:00',
                    'hora_fin' => '20:00',
                    'descanso_inicio' => '16:00',
                    'descanso_fin' => '17:00',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('horarios.update', $this->medico), $payload);
        $response->assertRedirect(route('medicos.show', $this->medico));

        $this->assertDatabaseHas('horarios_atencion', [
            'medico_id' => $this->medico->id,
            'dia_semana' => 1,
            'activo' => true,
        ]);
    }

    /**
     * CU5: Generar slots automáticos de 15 minutos respetando el descanso de 1 hora.
     */
    public function test_can_generate_slots_with_15min_intervals_and_rest_period(): void
    {
        // Asegurar que el médico tiene un horario para el próximo lunes
        $proximoLunes = now()->next(Carbon::MONDAY)->toDateString();

        HorarioAtencion::updateOrCreate(
            ['medico_id' => $this->medico->id, 'dia_semana' => 1],
            [
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'descanso_inicio' => '10:00',
                'descanso_fin' => '11:00',
                'duracion_slot_minutos' => 15,
                'activo' => true,
            ]
        );

        $response = $this->actingAs($this->admin)->post(route('horarios.generar', $this->medico), [
            'fecha_inicio' => $proximoLunes,
            'dias_a_generar' => 1,
        ]);

        $response->assertRedirect();

        // Verificar que los slots de 08:00 a 10:00 son disponibles
        $hasDisponible = Slot::where('medico_id', $this->medico->id)
            ->whereDate('fecha', $proximoLunes)
            ->where('hora_inicio', '08:00')
            ->where('hora_fin', '08:15')
            ->where('estado', 'disponible')
            ->exists();
        $this->assertTrue($hasDisponible);

        // Verificar que durante el descanso (10:00 - 11:00) los slots están en estado 'descanso'
        $hasDescanso = Slot::where('medico_id', $this->medico->id)
            ->whereDate('fecha', $proximoLunes)
            ->where('hora_inicio', '10:00')
            ->where('hora_fin', '10:15')
            ->where('estado', 'descanso')
            ->exists();
        $this->assertTrue($hasDescanso);
    }

    /**
     * CU5: El médico puede consultar y configurar su propio horario de atención.
     */
    public function test_doctor_can_view_own_schedule_and_edit_form(): void
    {
        $responseIndex = $this->actingAs($this->medico->user)->get(route('horarios.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee($this->medico->user->name);

        $responseEdit = $this->actingAs($this->medico->user)->get(route('horarios.edit', $this->medico));
        $responseEdit->assertStatus(200);
        $responseEdit->assertSee('Configuración de Agenda Semanal (CU5)');
    }

    /**
     * CU5: El médico no puede acceder ni modificar los horarios de otro médico (403).
     */
    public function test_doctor_cannot_view_or_modify_other_doctor_schedule(): void
    {
        $otroMedico = Medico::factory()->create();

        // 1. Intento de ver formulario de edición de otro médico
        $responseEdit = $this->actingAs($this->medico->user)->get(route('horarios.edit', $otroMedico));
        $responseEdit->assertStatus(403);

        // 2. Intento de actualizar horarios de otro médico
        $responseUpdate = $this->actingAs($this->medico->user)->put(route('horarios.update', $otroMedico), [
            'dias' => [
                1 => [
                    'activo' => '1',
                    'hora_inicio' => '08:00',
                    'hora_fin' => '14:00',
                    'descanso_inicio' => '12:00',
                    'descanso_fin' => '13:00',
                ],
            ],
        ]);
        $responseUpdate->assertStatus(403);

        // 3. Intento de generar slots para otro médico
        $responseGenerar = $this->actingAs($this->medico->user)->post(route('horarios.generar', $otroMedico));
        $responseGenerar->assertStatus(403);
    }

    /**
     * CU5: El médico actualiza su propio horario y es redirigido a la edición con éxito.
     */
    public function test_doctor_can_update_own_schedule_and_redirects_to_edit(): void
    {
        $payload = [
            'dias' => [
                1 => [
                    'activo' => '1',
                    'hora_inicio' => '08:00',
                    'hora_fin' => '13:00',
                    'descanso_inicio' => '11:00',
                    'descanso_fin' => '12:00',
                ],
            ],
        ];

        $response = $this->actingAs($this->medico->user)->put(route('horarios.update', $this->medico), $payload);
        $response->assertRedirect(route('horarios.edit', $this->medico));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('horarios_atencion', [
            'medico_id' => $this->medico->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00',
            'hora_fin' => '13:00',
            'descanso_inicio' => '11:00',
            'descanso_fin' => '12:00',
            'activo' => true,
        ]);
    }

    /**
     * CU5: No se permite superposición de horarios entre distintas especialidades del mismo médico.
     */
    public function test_cannot_update_schedule_if_overlapping_with_another_specialty_of_same_doctor(): void
    {
        $esp2 = Especialidad::where('id', '!=', $this->medico->especialidad_id)->first();

        // Crear segunda especialidad para el mismo médico (mismo user_id)
        $segundoMedico = Medico::create([
            'user_id' => $this->medico->user_id,
            'especialidad_id' => $esp2->id,
            'nro_registro_medico' => 'COL-MED-CARDIO-99',
            'estado' => 'activo',
        ]);

        // La segunda especialidad atiende los lunes de 13:00 a 17:00
        HorarioAtencion::create([
            'medico_id' => $segundoMedico->id,
            'dia_semana' => 1,
            'hora_inicio' => '13:00',
            'hora_fin' => '17:00',
            'descanso_inicio' => '15:00',
            'descanso_fin' => '16:00',
            'duracion_slot_minutos' => 15,
            'activo' => true,
        ]);

        // Intentar actualizar la primera especialidad para los lunes de 08:00 a 14:00 (se superpone de 13:00 a 14:00)
        $payload = [
            'dias' => [
                1 => [
                    'activo' => '1',
                    'hora_inicio' => '08:00',
                    'hora_fin' => '14:00',
                    'descanso_inicio' => '11:00',
                    'descanso_fin' => '12:00',
                ],
            ],
        ];

        $response = $this->actingAs($this->medico->user)->put(route('horarios.update', $this->medico), $payload);
        $response->assertSessionHasErrors('dias.1.hora_inicio');
    }

    /**
     * CU5: Cambios incompatibles con citas pendientes se rechazan; no se desplazan citas.
     */
    public function test_cannot_update_schedule_if_incompatible_with_pending_appointments(): void
    {
        $paciente = Paciente::first();
        $proximoLunes = now()->next(Carbon::MONDAY)->toDateString();

        // Slot para el próximo lunes a las 09:00
        $slot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => $proximoLunes,
            'hora_inicio' => '09:00',
            'hora_fin' => '09:15',
            'estado' => 'reservado',
        ]);

        // Cita pendiente programada
        $cita = Cita::create([
            'codigo_ficha' => 'FCH-TEST-COMPAT',
            'paciente_id' => $paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $slot->id,
            'fecha' => $proximoLunes,
            'hora' => '09:00',
            'estado' => 'programada',
            'motivo_consulta' => 'Control de compatibilidad de agenda',
        ]);

        // Caso 1: Intentar desactivar el lunes teniendo cita pendiente
        $payloadDesactivar = [
            'dias' => [
                1 => [
                    'activo' => '0',
                    'hora_inicio' => '08:00',
                    'hora_fin' => '14:00',
                    'descanso_inicio' => '12:00',
                ],
            ],
        ];
        $response1 = $this->actingAs($this->medico->user)->put(route('horarios.update', $this->medico), $payloadDesactivar);
        $response1->assertSessionHasErrors('dias.1.activo');

        // Caso 2: Intentar cambiar hora de inicio a las 10:00 (dejando la cita de las 09:00 fuera de la jornada)
        $payloadRango = [
            'dias' => [
                1 => [
                    'activo' => '1',
                    'hora_inicio' => '10:00',
                    'hora_fin' => '16:00',
                    'descanso_inicio' => '13:00',
                ],
            ],
        ];
        $response2 = $this->actingAs($this->medico->user)->put(route('horarios.update', $this->medico), $payloadRango);
        $response2->assertSessionHasErrors('dias.1.hora_inicio');

        // Caso 3: Intentar colocar el descanso entre las 09:00 y las 10:00 (colisionando con la cita)
        $payloadDescanso = [
            'dias' => [
                1 => [
                    'activo' => '1',
                    'hora_inicio' => '08:00',
                    'hora_fin' => '16:00',
                    'descanso_inicio' => '09:00',
                ],
            ],
        ];
        $response3 = $this->actingAs($this->medico->user)->put(route('horarios.update', $this->medico), $payloadDescanso);
        $response3->assertSessionHasErrors('dias.1.descanso_inicio');

        // Verificar que la cita NO fue desplazada, alterada ni cancelada
        $cita->refresh();
        $this->assertEquals('programada', $cita->estado);
        $this->assertEquals('09:00', substr($cita->hora, 0, 5));
        $this->assertEquals($slot->id, $cita->slot_id);
    }

    /**
     * CU6: Crear un bloqueo de agenda médica y verificar que los slots se bloquean.
     */
    public function test_can_create_agenda_block_and_block_slots(): void
    {
        $fecha = now()->addDays(2)->toDateString();

        // Crear un slot de prueba disponible
        $slot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '09:15',
            'estado' => 'disponible',
        ]);

        $response = $this->actingAs($this->admin)->post(route('bloqueos.store'), [
            'medico_id' => $this->medico->id,
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'tipo' => 'permiso',
            'motivo' => 'Asistencia a Congreso Médico Internacional',
        ]);

        $response->assertRedirect(route('bloqueos.index'));

        // El slot debe haber pasado a 'bloqueado'
        $this->assertEquals('bloqueado', $slot->fresh()->estado);

        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'CREAR_BLOQUEO_AGENDA',
            'modulo' => 'P2_Medicos',
        ]);
    }

    /**
     * CU6: Eliminar bloqueo de agenda y liberar los slots afectados.
     */
    public function test_can_delete_agenda_block_and_release_slots(): void
    {
        $fecha = now()->addDays(3)->toDateString();

        $slot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '09:15',
            'estado' => 'bloqueado',
        ]);

        $bloqueo = BloqueoAgenda::create([
            'medico_id' => $this->medico->id,
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'tipo' => 'capacitacion',
            'motivo' => 'Curso de Capacitación Interna',
            'registrado_por_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('bloqueos.destroy', $bloqueo));
        $response->assertRedirect(route('bloqueos.index'));

        // El slot bloqueado debe haber vuelto a estar disponible
        $this->assertEquals('disponible', $slot->fresh()->estado);
    }

    /**
     * CU12: Consulta de agenda médica interactiva y endpoint API de slots.
     */
    public function test_can_consult_agenda_view_and_api(): void
    {
        // 1. Vista web de agenda
        $response = $this->actingAs($this->admin)->get(route('agenda.show', [
            'medico_id' => $this->medico->id,
            'fecha' => today()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('Agenda Médica y Turnos');
        $response->assertSee($this->medico->user->name);

        // 2. Endpoint API de slots
        $apiResponse = $this->actingAs($this->admin)->getJson(route('api.agenda.slots', [
            'medico_id' => $this->medico->id,
            'fecha' => today()->toDateString(),
        ]));

        $apiResponse->assertStatus(200);
        $apiResponse->assertJsonStructure([
            'success',
            'medico',
            'fecha',
            'slots',
            'stats',
        ]);
    }
}
