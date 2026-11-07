<?php

namespace Tests\Feature;

use App\Mail\AvisoCitaMail;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P5\ExamenLaboratorio;
use App\Models\P5\OrdenLaboratorio;
use App\Models\P5\ResultadoLaboratorio;
use App\Models\P7\Notificacion;
use App\Models\User;
use App\Services\CitaWorkflowService;
use App\Services\NotificacionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificacionesMultiRolTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $recepcionista;

    protected User $medicoUser;

    protected Medico $medico;

    protected User $pacienteUser;

    protected Paciente $paciente;

    protected User $labUser;

    protected Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@medicita.com')->firstOrFail();
        $this->recepcionista = User::where('email', 'recepcion@medicita.com')->firstOrFail();
        $this->medicoUser = User::where('email', 'dr.perez@medicita.com')->firstOrFail();
        $this->medico = Medico::where('user_id', $this->medicoUser->id)->firstOrFail();
        $this->pacienteUser = User::where('email', 'paciente.demo@medicita.com')->firstOrFail();
        $this->paciente = Paciente::where('user_id', $this->pacienteUser->id)->firstOrFail();
        $this->labUser = User::where('email', 'lab@medicita.com')->firstOrFail();

        $this->slot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:15:00',
            'estado' => 'disponible',
            'sobrecupo' => false,
        ]);
    }

    /**
     * Verifica que cuando el paciente se presenta en recepción (checkin -> llegada),
     * el médico tratante reciba una notificación inmediata informando que está en sala de espera.
     */
    public function test_patient_arrival_at_reception_notifies_attending_doctor(): void
    {
        $cita = Cita::create([
            'codigo_ficha' => 'FCH-NOTIF-001',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'estado' => 'programada',
        ]);

        app(CitaWorkflowService::class)->ejecutar(
            referencia: $cita,
            user: $this->recepcionista,
            accion: 'llegada',
            datos: ['motivo' => 'Paciente en recepción', 'operacion_id' => (string) Str::uuid()]
        );

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->medicoUser->id,
            'tipo' => 'llegada_paciente',
            'leido' => false,
        ]);
    }

    /**
     * Verifica que cuando se emite una orden de laboratorio, el personal de laboratorio
     * (bioquímicos) reciba la notificación correspondiente.
     */
    public function test_lab_order_emission_notifies_biochemists(): void
    {
        $orden = OrdenLaboratorio::create([
            'codigo_orden' => 'ORD-NOTIF-001',
            'operacion_id' => (string) Str::uuid(),
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'estado' => 'pendiente',
            'prioridad' => 'normal',
        ]);

        $examen = ExamenLaboratorio::first();
        if ($examen) {
            $orden->examenes()->attach($examen->id, ['estado' => 'pendiente']);
        }

        NotificacionService::notificarOrdenLaboratorio($orden);

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->labUser->id,
            'tipo' => 'laboratorio_orden',
            'leido' => false,
        ]);
    }

    /**
     * Verifica que cuando el personal de laboratorio publica resultados de análisis,
     * el médico solicitante reciba una notificación indicando que los resultados están disponibles.
     */
    public function test_lab_results_publication_notifies_ordering_physician(): void
    {
        $orden = OrdenLaboratorio::create([
            'codigo_orden' => 'ORD-NOTIF-002',
            'operacion_id' => (string) Str::uuid(),
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'estado' => 'finalizada',
            'prioridad' => 'normal',
        ]);

        $resultado = ResultadoLaboratorio::create([
            'codigo_resultado' => 'RES-NOTIF-001',
            'orden_laboratorio_id' => $orden->id,
            'user_id' => $this->labUser->id,
            'version' => 1,
            'estado' => 'publicado',
            'operacion_id' => (string) Str::uuid(),
        ]);

        NotificacionService::notificarResultadoLaboratorio($resultado);

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->medicoUser->id,
            'tipo' => 'laboratorio',
            'leido' => false,
        ]);
    }

    /**
     * Verifica que tras 3 intentos fallidos de autenticación, la cuenta se bloquee
     * y el administrador del sistema reciba un aviso de seguridad.
     */
    public function test_account_lockout_after_three_failed_logins_notifies_admin(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'email' => $this->pacienteUser->email,
                'password' => 'PasswordIncorrecto123!',
            ]);
        }

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->admin->id,
            'tipo' => 'seguridad_bloqueo',
            'leido' => false,
        ]);
    }

    /**
     * Verifica que cuando una cita no atendida pasa a 'no_asistio' automáticamente,
     * el personal de recepción reciba un aviso para liberar o gestionar el espacio.
     */
    public function test_auto_inasistencia_notifies_reception_staff(): void
    {
        $slotPasado = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => now()->subDay()->toDateString(),
            'hora_inicio' => '08:00:00',
            'hora_fin' => '08:15:00',
            'estado' => 'ocupado',
            'sobrecupo' => false,
        ]);

        $citaPasada = Cita::create([
            'codigo_ficha' => 'FCH-NOTIF-999',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $slotPasado->id,
            'fecha' => now()->subDay()->toDateString(),
            'hora' => '08:00:00',
            'estado' => 'programada',
        ]);

        app(CitaWorkflowService::class)->procesarInasistenciasAutomaticas();

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->recepcionista->id,
            'tipo' => 'inasistencia_automatica',
            'leido' => false,
        ]);
    }

    /**
     * Verifica que un usuario con avisos no leídos visualice el banner en pantalla en el dashboard
     * y el contador con botón de acceso directo.
     */
    public function test_user_with_unread_notices_sees_on_screen_banner_in_layout(): void
    {
        Notificacion::create([
            'evento_id' => 'test:unread:banner:'.now()->timestamp,
            'user_id' => $this->admin->id,
            'tipo' => 'reserva',
            'titulo' => 'Aviso de prueba',
            'mensaje' => 'Tienes una actualización importante.',
            'leido' => false,
        ]);

        $response = $this->actingAs($this->admin)->get('/');

        $response->assertStatus(200);
        $response->assertSee('Tienes 1 nuevo aviso sin leer');
        $response->assertSee('Revisar Avisos (1)');
    }

    /**
     * Verifica que la plantilla de correo electrónico (AvisoCitaMail) se renderice
     * correctamente tanto con cita_id como sin cita_id (enlaces seguros a nivel general).
     */
    public function test_email_template_renders_properly_for_appointment_and_system_notices(): void
    {
        $cita = Cita::create([
            'codigo_ficha' => 'FCH-NOTIF-777',
            'paciente_id' => $this->paciente->id,
            'medico_id' => $this->medico->id,
            'slot_id' => $this->slot->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'estado' => 'programada',
        ]);

        // 1. Notificación vinculada a una cita
        $avisoCita = Notificacion::create([
            'evento_id' => 'mail:cita:test:1',
            'user_id' => $this->pacienteUser->id,
            'cita_id' => $cita->id,
            'tipo' => 'reserva',
            'titulo' => 'Confirmación de cita',
            'mensaje' => 'Su cita fue confirmada.',
            'leido' => false,
        ]);

        $mailCita = new AvisoCitaMail($avisoCita);
        $htmlCita = $mailCita->render();
        $this->assertStringContainsString('Confirmación de cita', $htmlCita);
        $this->assertStringContainsString('Ver Detalles en el Portal', $htmlCita);

        // 2. Notificación del sistema sin cita (p. ej. orden de lab, bloqueo, seguridad)
        $avisoSistema = Notificacion::create([
            'evento_id' => 'mail:sistema:test:2',
            'user_id' => $this->pacienteUser->id,
            'cita_id' => null,
            'tipo' => 'seguridad_bloqueo',
            'titulo' => 'Alerta de Seguridad',
            'mensaje' => 'Se detectó un intento sospechoso.',
            'leido' => false,
        ]);

        $mailSistema = new AvisoCitaMail($avisoSistema);
        $htmlSistema = $mailSistema->render();
        $this->assertStringContainsString('Alerta de Seguridad', $htmlSistema);
        $this->assertStringContainsString('Abrir Portal Clínico', $htmlSistema);
    }
}
