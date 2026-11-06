<?php

namespace Tests\Feature;

use App\Jobs\EnviarAviso;
use App\Mail\AvisoCitaMail;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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
        $this->admin->forceFill(['email_verified_at' => now()])->save();
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
        $response->assertRedirect();
        (new EnviarAviso(Notificacion::sole()->id))->handle();
        Mail::assertSent(AvisoCitaMail::class, function ($mail) use ($cita) {
            return $mail->hasTo($this->admin->email) &&
                   $mail->aviso->cita_id === $cita->id;
        });

        // Verificar que se registró la notificación en base de datos
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->paciente->user_id,
            'tipo' => 'reserva',
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
        $response->assertRedirect();
        (new EnviarAviso(Notificacion::sole()->id))->handle();
        Mail::assertSent(AvisoCitaMail::class, function ($mail) use ($motivo) {
            return $mail->hasTo($this->admin->email) &&
                   ! str_contains($mail->aviso->mensaje, $motivo);
        });

        // Verificar notificación en BD
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->paciente->user_id,
            'tipo' => 'cancelar',
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
            'nuevo_slot_id' => $nuevoSlot->id, 'aceptacion_paciente' => 1,
            'motivo_reprogramacion' => 'Cambio de turno solicitado por el paciente',
        ]);

        $response->assertRedirect();
        (new EnviarAviso(Notificacion::sole()->id))->handle();
        Mail::assertSent(AvisoCitaMail::class, function ($mail) {
            return $mail->hasTo($this->admin->email) &&
                   str_contains($mail->aviso->mensaje, '14:00');
        });

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->paciente->user_id,
            'tipo' => 'reprogramar',
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
        Queue::fake([EnviarAviso::class]);
        NotificacionService::registrarEvento($cita, (string) Str::uuid(), 'reserva');
        Queue::assertPushed(EnviarAviso::class);
        (new EnviarAviso(Notificacion::sole()->id))->handle();

        $this->assertDatabaseHas('notificaciones', ['cita_id' => $cita->id, 'estado_envio' => 'fallido', 'intentos' => 1]);
    }
}
