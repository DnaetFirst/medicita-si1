<?php

namespace Tests\Feature;

use App\Mail\CitaCanceladaMail;
use App\Mail\CitaConfirmadaMail;
use App\Mail\CitaReprogramadaMail;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P7\Notificacion;
use App\Models\User;
use App\Services\NotificacionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Etapa5Test extends TestCase
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

        // Crear paciente con correo electrónico y cuenta de usuario asociada
        $this->paciente = Paciente::create([
            'ci' => '7890123',
            'nombres' => 'Sebastián',
            'apellidos' => 'Vargas Pinto',
            'fecha_nacimiento' => '1998-05-12',
            'genero' => 'Masculino',
            'grupo_sanguineo' => 'O+',
            'telefono' => '77001122',
            'email' => 'sebastian.vargas@correo.com',
            'user_id' => $this->admin->id, // Vinculado a usuario
            'activo' => true,
        ]);

        $this->slot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => today()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '10:15',
            'estado' => 'disponible',
        ]);
    }

    /**
     * CU16: Envío de correo de confirmación al reservar cita médica (CU8).
     */
    public function test_sends_email_notification_upon_appointment_booking(): void
    {
        Mail::fake();

        $payload = [
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'motivo_consulta' => 'Consulta preventiva de rutina',
        ];

        $response = $this->actingAs($this->recepcionista)->post(route('citas.store'), $payload);

        $cita = Cita::where('slot_id', $this->slot->id)->first();
        $this->assertNotNull($cita);

        // Verificar que se envió el correo de confirmación
        Mail::assertSent(CitaConfirmadaMail::class, function ($mail) use ($cita) {
            return $mail->hasTo('sebastian.vargas@correo.com') &&
                   $mail->cita->id === $cita->id;
        });

        // Verificar que se registró la notificación en base de datos
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->paciente->user_id,
            'tipo' => 'cita_confirmada',
        ]);
    }

    /**
     * CU16: Envío de correo de cancelación con motivo justificado (CU11).
     */
    public function test_sends_email_notification_upon_appointment_cancellation(): void
    {
        Mail::fake();

        $this->slot->update(['estado' => 'reservado']);

        $cita = Cita::create([
            'codigo_ficha' => 'FCH-20261026-0099',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => $this->slot->fecha,
            'hora' => $this->slot->hora_inicio,
            'estado' => 'programada',
        ]);

        $motivo = 'Urgencia laboral impostergable del paciente';

        $response = $this->actingAs($this->recepcionista)->post(route('citas.cancelar', $cita), [
            'motivo_cancelacion' => $motivo,
        ]);

        // Verificar envío del correo de cancelación
        Mail::assertSent(CitaCanceladaMail::class, function ($mail) use ($motivo) {
            return $mail->hasTo('sebastian.vargas@correo.com') &&
                   $mail->motivo === $motivo;
        });

        // Verificar notificación en BD
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->paciente->user_id,
            'tipo' => 'cita_cancelada',
        ]);
    }

    /**
     * CU16: Envío de correo de reprogramación con detalles de nuevo turno (CU11).
     */
    public function test_sends_email_notification_upon_appointment_rescheduling(): void
    {
        Mail::fake();

        $this->slot->update(['estado' => 'reservado']);

        $cita = Cita::create([
            'codigo_ficha' => 'FCH-20261026-0088',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => $this->slot->fecha,
            'hora' => $this->slot->hora_inicio,
            'estado' => 'programada',
        ]);

        $nuevoSlot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => today()->addDays(4)->toDateString(),
            'hora_inicio' => '14:00',
            'hora_fin' => '14:15',
            'estado' => 'disponible',
        ]);

        $response = $this->actingAs($this->recepcionista)->post(route('citas.reprogramar.post', $cita), [
            'nuevo_slot_id' => $nuevoSlot->id,
            'motivo_reprogramacion' => 'Cambio de turno solicitado por el paciente',
        ]);

        Mail::assertSent(CitaReprogramadaMail::class, function ($mail) use ($nuevoSlot) {
            return $mail->hasTo('sebastian.vargas@correo.com') &&
                   $mail->citaNueva->slot_id === $nuevoSlot->id;
        });

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->paciente->user_id,
            'tipo' => 'reprogramacion',
        ]);
    }

    /**
     * CU16: Tolerancia a fallos — Si el servidor SMTP no está configurado o falla, el sistema no aborta la transacción.
     */
    public function test_graceful_handling_when_mailer_fails(): void
    {
        $cita = Cita::create([
            'codigo_ficha' => 'FCH-20261026-0077',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => $this->slot->fecha,
            'hora' => $this->slot->hora_inicio,
            'estado' => 'programada',
        ]);

        // Simular que Mail arroja una excepción
        Mail::shouldReceive('to->send')->andThrow(new \Exception('SMTP Connection timeout'));

        // El servicio debe atrapar la excepción y permitir que la app continúe
        NotificacionService::notificarConfirmacion($cita);

        $this->assertTrue(true, 'El servicio de notificación procesó el fallo sin interrumpir el flujo.');
    }
}
