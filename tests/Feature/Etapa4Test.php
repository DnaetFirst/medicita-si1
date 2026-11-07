<?php

namespace Tests\Feature;

use App\Models\P1\Rol;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P3\ResponsableAutorizado;
use App\Models\P4\Cita;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Etapa4Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $recepcionista;

    protected Medico $medico;

    protected Paciente $paciente;

    protected Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@medicita.com')->first();
        $this->recepcionista = User::where('email', 'recepcion@medicita.com')->first();
        $this->medico = Medico::with(['user', 'especialidad'])->first();

        // Crear paciente inicial para pruebas
        $this->paciente = Paciente::create([
            'ci' => '1029384',
            'nombres' => 'Mateo',
            'apellidos' => 'Suárez Peña',
            'fecha_nacimiento' => '2015-06-15', // Menor de edad
            'genero' => 'Masculino',
            'grupo_sanguineo' => 'O+',
            'telefono' => '71020304',
            'email' => 'mateo.suarez@correo.com',
            'contacto_emergencia_nombre' => 'Lucía Peña',
            'contacto_emergencia_telefono' => '71020399',
            'activo' => true,
        ]);

        // Crear slot disponible de prueba
        $this->slot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => today()->addDays(2)->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '09:15',
            'estado' => 'disponible',
        ]);
    }

    /**
     * CU7: Listado general de pacientes.
     */
    public function test_can_view_patients_index(): void
    {
        $response = $this->actingAs($this->recepcionista)->get(route('pacientes.index'));

        $response->assertStatus(200);
        $response->assertSee('Fichas de Pacientes (CU7)');
        $response->assertSee('Mateo Suárez Peña');
    }

    /**
     * CU7: Registro de nuevo paciente con responsable legal / tutor autorizado.
     */
    public function test_can_create_patient_with_authorized_responsible(): void
    {
        $data = [
            'ci' => '6543210',
            'nombres' => 'Valeria',
            'apellidos' => 'Roca Justiniano',
            'fecha_nacimiento' => '2018-03-20',
            'genero' => 'Femenino',
            'grupo_sanguineo' => 'A+',
            'telefono' => '72030405',
            'email' => 'valeria.roca@medicita.com',
            'tiene_responsable' => '1',
            'resp_ci' => '3210987',
            'resp_nombres' => 'Claudia Justiniano (Madre)',
            'resp_parentesco' => 'Madre',
            'resp_telefono' => '72030499',
            'resp_vigencia' => now()->addYear()->toDateString(),
        ];

        $response = $this->actingAs($this->recepcionista)->post(route('pacientes.store'), $data);

        $nuevoPaciente = Paciente::where('ci', '6543210')->first();
        $this->assertNotNull($nuevoPaciente);
        $response->assertRedirect(route('pacientes.show', $nuevoPaciente));

        $this->assertDatabaseHas('pacientes', ['ci' => '6543210']);
        $this->assertDatabaseHas('responsables_autorizados', [
            'paciente_id' => $nuevoPaciente->id,
            'ci' => '3210987',
            'parentesco' => 'Madre',
        ]);

        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'REGISTRAR_PACIENTE',
            'modulo' => 'P3_Pacientes',
        ]);
    }

    /**
     * CU7: Acreditar un nuevo responsable autorizado a un paciente existente y verificar vigencia.
     */
    public function test_can_add_authorized_responsible_to_patient(): void
    {
        $data = [
            'ci' => '4455667',
            'nombres' => 'Jorge Suárez (Padre)',
            'parentesco' => 'Padre',
            'telefono' => '70099887',
            'fecha_limite_vigencia' => now()->addMonths(6)->toDateString(),
        ];

        $response = $this->actingAs($this->recepcionista)->post(route('pacientes.responsables.store', $this->paciente), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('responsables_autorizados', [
            'paciente_id' => $this->paciente->id,
            'ci' => '4455667',
            'parentesco' => 'Padre',
        ]);

        $responsable = ResponsableAutorizado::where('ci', '4455667')->first();
        $this->assertTrue($responsable->isVigente());
    }

    /**
     * CU8: Acceso al wizard de solicitud de ficha y reserva de citas.
     */
    public function test_can_access_appointment_wizard(): void
    {
        $response = $this->actingAs($this->recepcionista)->get(route('citas.nueva'));

        $response->assertStatus(200);
        $response->assertSee('Solicitud de Ficha y Reserva de Citas (CU8)');
        $response->assertSee('Paso 1: Selecciona la Especialidad Médica');
    }

    /**
     * CU8: Reserva exitosa de ficha médica atómica y cambio de estado del slot a reservado.
     */
    public function test_can_reserve_appointment_slot_successfully(): void
    {
        $payload = [
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'motivo_consulta' => 'Control pediátrico semestral y chequeo general.',
        ];

        $response = $this->actingAs($this->recepcionista)->post(route('citas.store'), $payload);

        $cita = Cita::where('slot_id', $this->slot->id)->first();
        $this->assertNotNull($cita);
        $this->assertEquals('programada', $cita->estado);
        $this->assertStringStartsWith('FCH-', $cita->codigo_ficha);

        // El slot debe haber pasado a 'reservado'
        $this->assertEquals('reservado', $this->slot->fresh()->estado);

        $response->assertRedirect(route('citas.show', $cita));

        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'SOLICITAR_FICHA_CITA',
            'modulo' => 'P4_Citas',
        ]);
    }

    /**
     * CU8: Prevenir doble reserva concurrente en un slot ya ocupado.
     */
    public function test_cannot_reserve_already_occupied_slot(): void
    {
        // Ocupar slot previamente
        $this->slot->update(['estado' => 'reservado']);

        $payload = [
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'motivo_consulta' => 'Intento de reserva en turno ocupado',
        ];

        $response = $this->actingAs($this->recepcionista)->post(route('citas.store'), $payload);
        $response->assertStatus(422);
    }

    /**
     * CU9: Visualización de comprobante oficial de cita médica con código y datos.
     */
    public function test_can_view_appointment_voucher(): void
    {
        $cita = Cita::create([
            'codigo_ficha' => 'FCH-20261026-0001',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => $this->slot->fecha,
            'hora' => $this->slot->hora_inicio,
            'estado' => 'programada',
        ]);

        $response = $this->actingAs($this->recepcionista)->get(route('citas.show', $cita));

        $response->assertStatus(200);
        $response->assertSee('FCH-20261026-0001');
        $response->assertSee('Comprobante de Ficha Médica (CU9)');
        $response->assertSee($this->paciente->nombre_completo);
        $response->assertSee($this->medico->user->name);
    }

    /**
     * CU10: Control de asistencia — Check-in de recepción y consulta médica.
     */
    public function test_can_process_checkin_and_medical_consultation(): void
    {
        $cita = Cita::create([
            'codigo_ficha' => 'FCH-20261026-0002',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => $this->slot->fecha,
            'hora' => $this->slot->hora_inicio,
            'estado' => 'programada',
        ]);

        $this->travelTo($this->slot->fecha->copy()->setTime(8, 30));

        // 1. Recepcionista realiza check-in del paciente
        $responseCheckin = $this->actingAs($this->recepcionista)->post(route('citas.checkin', $cita));
        $responseCheckin->assertRedirect();
        $this->assertEquals('en_espera', $cita->fresh()->estado);
        $this->assertNotNull($cita->fresh()->hora_checkin);

        // 2. Médico inicia consulta
        $responseIniciar = $this->actingAs($this->medico->user)->post(route('citas.iniciar', $cita));
        $responseIniciar->assertRedirect();
        $this->assertEquals('en_atencion', $cita->fresh()->estado);

        // 3. Médico finaliza atención con diagnóstico
        $responseFinalizar = $this->actingAs($this->medico->user)->post(route('citas.finalizar', $cita), [
            'observaciones_atencion' => 'Paciente en buen estado general. Se indica tratamiento preventivo.',
        ]);
        $responseFinalizar->assertRedirect();
        $this->assertEquals('atendida', $cita->fresh()->estado);
        $this->assertNull($cita->fresh()->observaciones_atencion);
    }

    /**
     * CU11: Cancelación de cita con motivo obligatorio y liberación inmediata del slot.
     */
    public function test_can_cancel_appointment_and_free_slot(): void
    {
        $this->slot->update(['estado' => 'reservado']);

        $cita = Cita::create([
            'codigo_ficha' => 'FCH-20261026-0003',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => $this->slot->fecha,
            'hora' => $this->slot->hora_inicio,
            'estado' => 'programada',
        ]);

        $response = $this->actingAs($this->recepcionista)->post(route('citas.cancelar', $cita), [
            'motivo_cancelacion' => 'Paciente informa choque vehicular y no podrá acudir a la hora fijada.',
        ]);

        $response->assertRedirect(route('citas.show', $cita));

        // La cita debe quedar cancelada con motivo
        $this->assertEquals('cancelada', $cita->fresh()->estado);
        $this->assertEquals('Paciente informa choque vehicular y no podrá acudir a la hora fijada.', $cita->fresh()->motivo_cancelacion);

        // El slot debe volver a estar disponible
        $this->assertEquals('disponible', $this->slot->fresh()->estado);

        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'CANCELAR',
            'modulo' => 'P4_Citas',
        ]);
    }

    /**
     * CU11: Reprogramación de cita hacia un nuevo slot disponible.
     */
    public function test_can_reschedule_appointment_to_new_slot(): void
    {
        $this->slot->update(['estado' => 'reservado']);

        $citaOriginal = Cita::create([
            'codigo_ficha' => 'FCH-20261026-0004',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => $this->slot->fecha,
            'hora' => $this->slot->hora_inicio,
            'estado' => 'programada',
            'motivo_consulta' => 'Revisión periódica',
        ]);

        // Crear un nuevo slot disponible para reprogramar
        $nuevoSlot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => today()->addDays(3)->toDateString(),
            'hora_inicio' => '11:00',
            'hora_fin' => '11:15',
            'estado' => 'disponible',
        ]);

        $response = $this->actingAs($this->recepcionista)->post(route('citas.reprogramar.post', $citaOriginal), [
            'nuevo_slot_id' => $nuevoSlot->id, 'aceptacion_paciente' => 1,
            'motivo_reprogramacion' => 'Cruce de horario con jornada escolar del menor.',
        ]);

        $citaNueva = Cita::where('slot_id', $nuevoSlot->id)->first();
        $this->assertNotNull($citaNueva);
        $response->assertRedirect(route('citas.show', $citaNueva));

        // El slot original debe haberse liberado
        $this->assertEquals('disponible', $this->slot->fresh()->estado);

        // El nuevo slot debe haber pasado a reservado
        $this->assertEquals('reservado', $nuevoSlot->fresh()->estado);

        // La reprogramación conserva la identidad de la cita.
        $this->assertEquals('programada', $citaOriginal->fresh()->estado);
        $this->assertEquals($citaOriginal->id, $citaNueva->id);
        $this->assertDatabaseCount('citas', 1);

        $this->assertDatabaseHas('bitacoras', [
            'accion' => 'REPROGRAMAR',
            'modulo' => 'P4_Citas',
        ]);
    }

    /**
     * CU7 + CU1: Creación de paciente genera usuario con CI como contraseña inicial y flag de cambio obligatorio.
     */
    public function test_patient_creation_creates_user_with_ci_password_and_security_flag(): void
    {
        $ci = '9871234';
        $data = [
            'ci' => $ci,
            'nombres' => 'Marcelo',
            'apellidos' => 'Claure',
            'fecha_nacimiento' => '1995-04-12',
            'genero' => 'Masculino',
            'telefono' => '78912345',
            'email' => 'marcelo.claure@medicita.com',
        ];

        $response = $this->actingAs($this->recepcionista)->post(route('pacientes.store'), $data);
        $response->assertRedirect();

        $paciente = Paciente::where('ci', $ci)->first();
        $this->assertNotNull($paciente);
        $this->assertNotNull($paciente->user_id);

        $usuario = $paciente->user;
        $this->assertEquals($ci, $usuario->ci);
        $this->assertTrue($usuario->isPaciente());
        $this->assertTrue($usuario->debe_cambiar_password);

        // Verificar que puede iniciar sesión usando su CI tanto en identificador como en contraseña
        $this->post(route('login.post'), [
            'email' => $ci,
            'password' => $ci,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);

        // Cambiar contraseña por una personal
        $responseCambio = $this->actingAs($usuario)->post(route('perfil.password'), [
            'password' => 'NuevaClaveSegura123!',
            'password_confirmation' => 'NuevaClaveSegura123!',
        ]);

        $responseCambio->assertRedirect();
        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
    }

    /**
     * CU7: Revocación de tutoría preserva datos del paciente y mantiene cuenta autónoma activa.
     */
    public function test_tutor_revocation_preserves_patient_user_and_records(): void
    {
        $ciTutor = '7711223';
        $responsable = $this->paciente->responsables()->create([
            'ci' => $ciTutor,
            'nombres' => 'Tutor Temporal',
            'parentesco' => 'Tutor Legal',
            'telefono' => '70011223',
            'fecha_limite_vigencia' => now()->addMonths(6)->toDateString(),
            'activo' => true,
        ]);

        $response = $this->actingAs($this->recepcionista)->delete(route('pacientes.responsables.destroy', [$this->paciente, $responsable]));
        $response->assertRedirect();

        $this->assertFalse($responsable->fresh()->activo);
        $this->assertNotNull($this->paciente->fresh()->user_id);
        $this->assertEquals('activo', $this->paciente->fresh()->user->estado);
    }

    /**
     * CU2: Quitar rol paciente en creación de usuarios y exigir estándar de seguridad en contraseñas.
     */
    public function test_user_registration_rejects_patient_role_and_enforces_password_complexity(): void
    {
        $rolPaciente = Rol::where('slug', 'paciente')->first();
        $rolAdmin = Rol::where('slug', 'admin')->first();

        // 1. Intentar crear rol paciente desde /usuarios debe ser rechazado
        $responseRolPaciente = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Intento Paciente',
            'ci' => '99887711',
            'email' => 'intento.paciente@medicita.com',
            'rol_id' => $rolPaciente->id,
            'password' => 'PasswordSegura123!',
            'password_confirmation' => 'PasswordSegura123!',
            'estado' => 'activo',
        ]);
        $responseRolPaciente->assertSessionHasErrors('rol_id');

        // 2. Intentar registrar con contraseña débil (sin números ni símbolos)
        $responsePassDebil = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Usuario Clave Debil',
            'ci' => '99887722',
            'email' => 'clave.debil@medicita.com',
            'rol_id' => $rolAdmin->id,
            'password' => 'sololetrasminusculas',
            'password_confirmation' => 'sololetrasminusculas',
            'estado' => 'activo',
        ]);
        $responsePassDebil->assertSessionHasErrors('password');
    }

    /**
     * CU7: La creación de paciente exige sí o sí correo electrónico para vincular su cuenta de usuario.
     */
    public function test_patient_registration_requires_email(): void
    {
        $response = $this->actingAs($this->recepcionista)->post(route('pacientes.store'), [
            'ci' => '44556677',
            'nombres' => 'Sin',
            'apellidos' => 'Correo',
            'fecha_nacimiento' => '1998-05-10',
            'telefono' => '78900011',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
