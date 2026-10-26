<?php

namespace Tests\Feature;

use App\Models\P1\Bitacora;
use App\Models\P2\BloqueoAgenda;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\User;
use App\Services\SlotGeneratorService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Medico $medico;

    protected Paciente $paciente;

    protected Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $this->admin = User::where('email', 'admin@medicita.com')->firstOrFail();
        $this->medico = Medico::firstOrFail();
        $this->paciente = Paciente::firstOrFail();
        $this->slot = Slot::create([
            'medico_id' => $this->medico->id,
            'fecha' => '2026-11-02',
            'hora_inicio' => '09:00',
            'hora_fin' => '09:15',
            'estado' => 'disponible',
        ]);
        $this->actingAs($this->admin);
    }

    /** @return array{paciente_id: int, medico_id: int, slot_id: int} */
    protected function reservation(): array
    {
        return ['paciente_id' => $this->paciente->id, 'medico_id' => $this->medico->id, 'slot_id' => $this->slot->id];
    }

    protected function appointment(): Cita
    {
        $this->post(route('citas.store'), $this->reservation())->assertRedirect();

        return Cita::where('slot_id', $this->slot->id)->firstOrFail();
    }

    public function test_cancelled_slot_can_be_booked_again_with_history_preserved(): void
    {
        $original = $this->appointment();
        $this->post(route('citas.cancelar', $original), ['motivo_cancelacion' => 'Cambio de planes'])->assertRedirect();
        $this->post(route('citas.store'), $this->reservation())->assertRedirect();
        $this->assertDatabaseCount('citas', 2);
        $this->assertSame('cancelada', $original->fresh()->estado);
        $this->assertSame('programada', $this->slot->fresh()->cita->estado);
        $this->assertNotEquals($original->id, $this->slot->fresh()->cita->id);
    }

    public function test_specialty_update_and_delete_bind_the_requested_record(): void
    {
        $specialty = Especialidad::create(['nombre' => 'Prueba', 'codigo' => 'TEST', 'color' => '#112233', 'activo' => true]);
        $this->put(route('especialidades.update', $specialty), ['nombre' => 'Actualizada', 'codigo' => 'TEST', 'color' => '#112233'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Actualizada', $specialty->fresh()->nombre);
        $this->delete(route('especialidades.destroy', $specialty))->assertRedirect();
        $this->assertModelMissing($specialty);
        $this->delete(route('especialidades.destroy', $this->medico->especialidad))->assertSessionHas('error');
        $this->assertModelExists($this->medico->especialidad);
    }

    public function test_failed_inline_reservation_does_not_leave_a_patient(): void
    {
        $this->slot->update(['estado' => 'reservado']);
        $this->post(route('citas.store'), array_merge($this->reservation(), [
            'crear_paciente_nuevo' => 1, 'nuevo_ci' => 'NO-ORPHAN', 'nuevo_nombres' => 'Nuevo',
            'nuevo_apellidos' => 'Paciente', 'nuevo_fecha_nacimiento' => '2000-01-01', 'nuevo_telefono' => '70000001',
        ]))->assertUnprocessable();
        $this->assertDatabaseMissing('pacientes', ['ci' => 'NO-ORPHAN']);
    }

    public function test_inline_reservation_creates_patient_and_appointment_together(): void
    {
        $this->post(route('citas.store'), array_merge($this->reservation(), [
            'crear_paciente_nuevo' => 1, 'nuevo_ci' => 'NEW-PATIENT', 'nuevo_nombres' => 'Nuevo',
            'nuevo_apellidos' => 'Paciente', 'nuevo_fecha_nacimiento' => '2000-01-01', 'nuevo_telefono' => '70000001',
        ]))->assertRedirect();
        $patient = Paciente::where('ci', 'NEW-PATIENT')->firstOrFail();
        $this->assertDatabaseHas('citas', ['paciente_id' => $patient->id, 'slot_id' => $this->slot->id]);
    }

    public function test_reservations_reject_past_slots_and_inactive_patients(): void
    {
        $this->slot->update(['fecha' => today()->subDay()]);
        $this->post(route('citas.store'), $this->reservation())->assertUnprocessable();
        $this->slot->update(['fecha' => today()->addDay()]);
        $this->paciente->update(['activo' => false]);
        $this->postJson(route('citas.store'), $this->reservation())->assertUnprocessable()->assertJsonValidationErrors('paciente_id');
        $this->assertDatabaseCount('citas', 0);
    }

    public function test_reservations_reject_mismatched_doctor_and_inactive_specialty(): void
    {
        $other = $this->medico->replicate();
        $other->user_id = User::factory()->create()->id;
        $other->nro_registro_medico = 'OTHER';
        $other->save();
        $this->post(route('citas.store'), array_merge($this->reservation(), ['medico_id' => $other->id]))->assertUnprocessable();
        $this->medico->especialidad->update(['activo' => false]);
        $this->post(route('citas.store'), $this->reservation())->assertUnprocessable();
        $this->assertSame('disponible', $this->slot->fresh()->estado);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidTransitions(): array
    {
        return [
            'start before checkin' => ['programada', 'iniciar'],
            'finish before starting' => ['confirmada', 'finalizar'],
            'absent after arrival' => ['confirmada', 'inasistencia'],
            'restart completed' => ['atendida', 'iniciar'],
            'finish cancelled' => ['cancelada', 'finalizar'],
            'absent completed' => ['atendida', 'inasistencia'],
        ];
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_appointment_transitions_preserve_state(string $state, string $action): void
    {
        $cita = $this->appointment();
        $cita->update(['estado' => $state]);
        $this->post(route('citas.'.$action, $cita))->assertUnprocessable();
        $this->assertSame($state, $cita->fresh()->estado);
    }

    public function test_completed_appointment_cannot_be_checked_in_or_rescheduled(): void
    {
        $cita = $this->appointment();
        $cita->update(['estado' => 'atendida']);
        $this->post(route('citas.checkin', $cita))->assertSessionHas('error');
        $this->post(route('citas.reprogramar.post', $cita), ['nuevo_slot_id' => $this->slot->id, 'motivo_reprogramacion' => 'Cambio'])->assertUnprocessable();
        $this->assertSame('atendida', $cita->fresh()->estado);
        $this->assertDatabaseCount('citas', 1);
    }

    public function test_blocks_respect_hours_and_overlapping_blocks(): void
    {
        $outside = $this->slot->replicate();
        $outside->hora_inicio = '10:00';
        $outside->hora_fin = '10:15';
        $outside->save();
        $payload = ['medico_id' => $this->medico->id, 'fecha_inicio' => '2026-11-02 09:05', 'fecha_fin' => '2026-11-02 09:10', 'tipo' => 'permiso', 'motivo' => 'Bloqueo parcial'];
        $this->post(route('bloqueos.store'), $payload)->assertRedirect();
        $first = BloqueoAgenda::firstOrFail();
        $this->post(route('bloqueos.store'), $payload)->assertRedirect();
        $this->assertSame('bloqueado', $this->slot->fresh()->estado);
        $this->assertSame('disponible', $outside->fresh()->estado);
        $this->delete(route('bloqueos.destroy', $first))->assertRedirect();
        $this->assertSame('bloqueado', $this->slot->fresh()->estado);
        $this->delete(route('bloqueos.destroy', BloqueoAgenda::firstOrFail()))->assertRedirect();
        $this->assertSame('disponible', $this->slot->fresh()->estado);
    }

    public function test_cancel_does_not_reopen_a_blocked_slot(): void
    {
        $cita = $this->appointment();
        $this->post(route('bloqueos.store'), ['medico_id' => $this->medico->id, 'fecha_inicio' => '2026-11-02 08:00', 'fecha_fin' => '2026-11-02 10:00', 'tipo' => 'permiso', 'motivo' => 'Ausencia'])->assertRedirect();
        $this->post(route('citas.cancelar', $cita), ['motivo_cancelacion' => 'Medico ausente'])->assertRedirect();
        $this->assertSame('bloqueado', $this->slot->fresh()->estado);
    }

    public function test_schedule_changes_reconcile_existing_slots_and_preserve_reservations(): void
    {
        $generator = app(SlotGeneratorService::class);
        $generator->generarSlotsParaFecha($this->medico, '2026-11-02');
        $this->appointment();
        $this->put(route('horarios.update', $this->medico), ['dias' => [1 => ['activo' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '14:00', 'descanso_inicio' => '11:00']]])->assertSessionHasNoErrors();
        $this->assertSame('reservado', $this->slot->fresh()->estado);
        $this->assertDatabaseHas('slots', ['fecha' => '2026-11-02 00:00:00', 'hora_inicio' => '08:00', 'estado' => 'bloqueado']);
        $this->assertDatabaseHas('slots', ['fecha' => '2026-11-02 00:00:00', 'hora_inicio' => '11:00', 'estado' => 'descanso']);
    }

    public function test_invalid_schedule_does_not_partially_update_days(): void
    {
        $this->putJson(route('horarios.update', $this->medico), ['dias' => [
            1 => ['activo' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '14:00', 'descanso_inicio' => '12:00'],
            2 => ['activo' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '14:00', 'descanso_inicio' => '13:30'],
        ]])->assertUnprocessable();
        $this->assertDatabaseHas('horarios_atencion', ['medico_id' => $this->medico->id, 'dia_semana' => 1, 'hora_inicio' => '08:00']);
    }

    public function test_inactive_user_cannot_continue_using_an_existing_session(): void
    {
        $this->admin->update(['estado' => 'inactivo']);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_failed_login_does_not_flash_password_and_expired_lock_resets_attempts(): void
    {
        auth()->logout();
        $this->admin->update(['intentos_fallidos' => 3, 'bloqueado_hasta' => now()->subMinute()]);
        $this->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'wrong'])->assertSessionMissing('_old_input.password');
        $this->assertSame(1, $this->admin->fresh()->intentos_fallidos);
        $this->assertNull($this->admin->fresh()->bloqueado_hasta);
    }

    public function test_responsible_cannot_be_revoked_through_another_patient(): void
    {
        $responsable = $this->paciente->responsables()->firstOrFail();
        $other = $this->paciente->replicate();
        $other->ci = 'OTHER-PATIENT';
        $other->user_id = null;
        $other->save();
        $this->delete(route('pacientes.responsables.destroy', [$other, $responsable]))->assertNotFound();
        $this->assertTrue($responsable->fresh()->activo);
    }

    public function test_all_administrative_screens_render(): void
    {
        foreach (['dashboard', 'usuarios.index', 'usuarios.create', 'especialidades.index', 'medicos.index', 'medicos.create', 'horarios.index', 'bloqueos.index', 'pacientes.index', 'pacientes.create', 'citas.index', 'citas.nueva', 'agenda.show', 'bitacora.index', 'bitacora.export'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('usuarios.edit', $this->admin))->assertOk()->assertSee('Mostrar contrase', false);
        $this->get(route('medicos.edit', $this->medico))->assertOk();
        $this->get(route('horarios.edit', $this->medico))->assertOk();
        $this->get(route('pacientes.edit', $this->paciente))->assertOk();
    }

    public function test_doctor_cannot_view_or_modify_another_doctors_appointments(): void
    {
        $cita = $this->appointment();
        $doctorUser = User::factory()->create(['rol_id' => $this->medico->user->rol_id, 'estado' => 'activo']);
        $this->actingAs($doctorUser);
        $this->get(route('citas.show', $cita))->assertForbidden();
        $this->post(route('citas.checkin', $cita))->assertForbidden();
        $this->getJson(route('api.agenda.slots', ['medico_id' => $this->medico->id, 'fecha' => '2026-11-02']))->assertForbidden();
        $this->get(route('citas.index'))->assertOk()->assertDontSee($cita->codigo_ficha);
        $this->assertSame('programada', $cita->fresh()->estado);
    }

    public function test_doctor_can_access_own_agenda_and_appointment(): void
    {
        $cita = $this->appointment();
        $this->actingAs($this->medico->user);
        $this->get(route('citas.show', $cita))->assertOk();
        $this->get(route('agenda.show'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('href="/usuarios"', false);
        $this->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_patient_and_reception_navigation_match_permissions(): void
    {
        $this->actingAs($this->paciente->user);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('href="/citas/nueva"', false);
        $this->get(route('pacientes.index'))->assertForbidden();
        $this->actingAs(User::where('email', 'recepcion@medicita.com')->firstOrFail());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('href="/usuarios"', false);
        $this->get(route('bitacora.index'))->assertForbidden();
    }

    public function test_rescheduling_can_be_repeated_without_reusing_cancelled_appointment(): void
    {
        $original = $this->appointment();
        $newSlot = $this->slot->replicate();
        $newSlot->hora_inicio = '10:00';
        $newSlot->hora_fin = '10:15';
        $newSlot->estado = 'disponible';
        $newSlot->save();
        $this->post(route('citas.reprogramar.post', $original), ['nuevo_slot_id' => $newSlot->id, 'motivo_reprogramacion' => 'Cambio de horario'])->assertRedirect();
        $replacement = Cita::where('slot_id', $newSlot->id)->firstOrFail();
        $this->post(route('citas.reprogramar.post', $original), ['nuevo_slot_id' => $this->slot->id, 'motivo_reprogramacion' => 'Reintento'])->assertUnprocessable();
        $this->post(route('citas.reprogramar.post', $replacement), ['nuevo_slot_id' => $this->slot->id, 'motivo_reprogramacion' => 'Volver al horario original'])->assertRedirect();
        $this->assertDatabaseCount('citas', 3);
        $this->assertSame('reservado', $this->slot->fresh()->estado);
        $this->assertSame('disponible', $newSlot->fresh()->estado);
    }

    public function test_create_user_and_doctor_with_optional_fields_omitted(): void
    {
        $this->post(route('usuarios.store'), [
            'name' => 'Nueva cuenta', 'ci' => 'NEW-USER', 'email' => 'new@example.com',
            'rol_id' => $this->admin->rol_id, 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'estado' => 'activo',
        ])->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));
        $this->post(route('medicos.store'), [
            'name' => 'Nuevo medico', 'ci' => 'NEW-DOCTOR', 'email' => 'doctor@example.com',
            'especialidad_id' => $this->medico->especialidad_id, 'nro_registro_medico' => 'NEW-REG',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'estado' => 'activo',
        ])->assertSessionHasNoErrors()->assertRedirect(route('medicos.index'));
        $this->assertDatabaseHas('medicos', ['nro_registro_medico' => 'NEW-REG']);
    }

    public function test_audit_export_applies_action_and_date_filters(): void
    {
        Bitacora::create(['accion' => 'INCLUDED', 'modulo' => 'TEST', 'created_at' => now()]);
        Bitacora::create(['accion' => 'EXCLUDED', 'modulo' => 'TEST', 'created_at' => now()]);
        Bitacora::create(['accion' => 'INCLUDED_OLD', 'modulo' => 'TEST', 'created_at' => now()->subDays(2)]);
        $response = $this->get(route('bitacora.export', ['accion' => 'INCLUDED', 'desde' => today()->toDateString()]));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('INCLUDED', $csv);
        $this->assertStringNotContainsString('EXCLUDED', $csv);
        $this->assertStringNotContainsString('INCLUDED_OLD', $csv);
    }

    public function test_agenda_and_wizard_reject_malformed_dates(): void
    {
        $this->getJson(route('agenda.show', ['fecha' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('citas.nueva', ['medico_id' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('api.agenda.slots', ['medico_id' => $this->medico->id, 'fecha' => 'invalid']))->assertUnprocessable();
    }

    public function test_regenerating_a_day_is_idempotent_and_does_not_release_reserved_slots(): void
    {
        $this->appointment();
        $generator = app(SlotGeneratorService::class);
        $generator->generarSlotsParaFecha($this->medico, '2026-11-02');
        $firstCount = Slot::count();
        $generator->generarSlotsParaFecha($this->medico, '2026-11-02');
        $this->assertSame($firstCount, Slot::count());
        $this->assertSame('reservado', $this->slot->fresh()->estado);
        $staleSlot = $this->slot->fresh();
        $staleSlot->estado = 'disponible';
        $generator->liberarSlot($staleSlot, preservarReserva: true);
        $this->assertSame('reservado', $this->slot->fresh()->estado);
    }
}
