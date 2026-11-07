<?php

namespace Tests\Feature;

use App\Jobs\EnviarAviso;
use App\Mail\AvisoCitaMail;
use App\Models\P1\Rol;
use App\Models\P2\BloqueoAgenda;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\AtencionMedica;
use App\Models\P3\Derivacion;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P7\Notificacion;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CicloDosEtapaUnoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 08:00:00');
        $this->seed(RolSeeder::class);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['rol_id' => Rol::where('slug', $rol)->firstOrFail()->id, 'estado' => 'activo']);
    }

    /** @return array<string, mixed> */
    private function nota(Cita $cita, int $version = 0): array
    {
        return ['cita_id' => $cita->id, 'version' => $version, 'operacion_id' => (string) Str::uuid(),
            'motivo_consulta' => 'Control de seguimiento', 'diagnostico' => 'Diagnostico privado', 'antecedentes' => 'Antecedente privado'];
    }

    public function test_confirmation_arrival_start_and_finish_have_distinct_actors_and_timestamps(): void
    {
        $pac = $this->usuario('paciente');
        $cita = Cita::factory()->create(['paciente_id' => Paciente::factory()->create(['user_id' => $pac->id]), 'fecha' => today()]);
        $cita->slot->update(['fecha' => today()]);
        $rec = $this->usuario('recepcionista');

        $this->actingAs($pac)->post(route('citas.confirmar', $cita))->assertRedirect();
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'confirmada', 'hora_llegada' => null]);
        $this->actingAs($rec)->post(route('citas.checkin', $cita))->assertRedirect();
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'en_espera', 'hora_llegada' => '2026-11-06 08:00:00']);
        $this->actingAs($cita->medico->user)->post(route('citas.iniciar', $cita))->assertRedirect();
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'en_atencion', 'hora_inicio_atencion' => '2026-11-06 08:00:00']);
        $this->post(route('citas.finalizar', $cita))->assertRedirect();
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'atendida', 'hora_fin_atencion' => '2026-11-06 08:00:00']);
        $this->assertDatabaseCount('historial_citas', 4);
    }

    /** @return array<string, array{string}> */
    public static function nonClinicalRoles(): array
    {
        return ['admin' => ['admin'], 'superadmin' => ['superadmin'], 'recepcion' => ['recepcionista'], 'paciente' => ['paciente'], 'lab' => ['lab']];
    }

    #[DataProvider('nonClinicalRoles')]
    public function test_non_doctors_cannot_read_or_write_clinical_records(string $rol): void
    {
        $cita = Cita::factory()->create(['estado' => 'en_atencion']);
        $user = $this->usuario($rol);
        $cita->paciente->update(['user_id' => $user->id]);
        $this->actingAs($user)->get(route('historia.show', $cita->paciente))->assertForbidden();
        $this->post(route('historia.store', $cita->paciente), $this->nota($cita))->assertForbidden();
        $this->assertDatabaseCount('atenciones_medicas', 0);
    }

    public function test_unrelated_doctor_and_patient_cannot_access_another_record(): void
    {
        $cita = Cita::factory()->create();
        $otro = Medico::factory()->create();
        $this->actingAs($otro->user)->get(route('historia.show', $cita->paciente))->assertForbidden();
        $this->actingAs($this->usuario('paciente'))->get(route('historial.index', ['paciente_id' => $cita->paciente_id]))->assertForbidden();
        $this->get(route('citas.show', $cita))->assertForbidden();
        $this->assertDatabaseCount('historial_citas', 0);
    }

    public function test_notes_require_started_visit_and_preserve_original_on_correction(): void
    {
        $cita = Cita::factory()->create();
        $payload = $this->nota($cita);
        $this->actingAs($cita->medico->user)->post(route('historia.store', $cita->paciente), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('historias_clinicas', 0);
        $cita->update(['estado' => 'en_atencion']);
        $this->post(route('historia.store', $cita->paciente), $payload)->assertRedirect();
        $this->post(route('historia.store', $cita->paciente), $payload)->assertRedirect();
        $original = AtencionMedica::sole();
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'en_atencion']);
        $correccion = array_merge($this->nota($cita, 1), ['corrige_id' => $original->id, 'motivo_correccion' => 'Aclaración de registro', 'diagnostico' => 'Diagnostico corregido']);
        $this->post(route('historia.store', $cita->paciente), $correccion)->assertRedirect();
        $this->assertDatabaseCount('historias_clinicas', 1);
        $this->assertDatabaseCount('atenciones_medicas', 2);
        $this->assertDatabaseHas('atenciones_medicas', ['id' => $original->id, 'diagnostico' => 'Diagnostico privado', 'es_correccion' => false]);
        $this->assertDatabaseHas('atenciones_medicas', ['corrige_id' => $original->id, 'diagnostico' => 'Diagnostico corregido']);
        $this->get(route('historia.show', $cita->paciente))->assertOk()->assertSee('Diagnostico corregido')->assertSee('Diagnostico privado');
    }

    public function test_stale_history_version_and_duplicate_original_do_not_overwrite_notes(): void
    {
        $cita = Cita::factory()->create(['estado' => 'en_atencion']);
        $this->actingAs($cita->medico->user)->post(route('historia.store', $cita->paciente), $this->nota($cita))->assertRedirect();
        $this->post(route('historia.store', $cita->paciente), $this->nota($cita))->assertConflict();
        $this->post(route('historia.store', $cita->paciente), $this->nota($cita, 1))->assertConflict();
        $this->assertDatabaseCount('atenciones_medicas', 1);
        $this->assertDatabaseHas('historias_clinicas', ['paciente_id' => $cita->paciente_id, 'version' => 1]);
    }

    public function test_derivation_is_idempotent_and_does_not_book_a_visit(): void
    {
        $nota = AtencionMedica::factory()->create();
        $payload = ['especialidad_id' => $nota->cita->medico->especialidad_id, 'motivo' => 'Evaluación complementaria', 'operacion_id' => (string) Str::uuid()];
        $this->actingAs($nota->autor)->post(route('historia.derivar', $nota), $payload)->assertRedirect();
        $this->post(route('historia.derivar', $nota), $payload)->assertRedirect();
        $this->assertDatabaseCount('derivaciones', 1);
        $this->assertDatabaseCount('citas', 1);
        $this->assertDatabaseHas('derivaciones', ['atencion_medica_id' => $nota->id, 'estado' => 'emitida']);
    }

    public function test_reprogramming_preserves_id_code_and_records_old_and_new_slots(): void
    {
        $cita = Cita::factory()->create();
        $nuevoMedico = Medico::factory()->create(['especialidad_id' => $cita->medico->especialidad_id]);
        $slot = Slot::factory()->create(['medico_id' => $nuevoMedico->id]);
        $payload = ['nuevo_slot_id' => $slot->id, 'motivo_reprogramacion' => 'Acuerdo con paciente', 'aceptacion_paciente' => 1, 'version' => 1, 'operacion_id' => (string) Str::uuid()];
        $this->actingAs($this->usuario('recepcionista'))->post(route('citas.reprogramar.post', $cita), $payload)->assertRedirect();
        $this->post(route('citas.reprogramar.post', $cita), $payload)->assertRedirect();
        $this->assertDatabaseCount('citas', 1);
        $this->assertDatabaseCount('historial_citas', 1);
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'codigo_ficha' => $cita->codigo_ficha, 'slot_id' => $slot->id, 'medico_id' => $nuevoMedico->id]);
        $this->assertDatabaseHas('historial_citas', ['cita_id' => $cita->id, 'slot_anterior_id' => $cita->slot_id, 'slot_nuevo_id' => $slot->id, 'aceptacion_paciente' => true]);
        $this->assertDatabaseHas('slots', ['id' => $cita->slot_id, 'estado' => 'disponible']);
    }

    public function test_failed_reprogramming_preserves_previous_reservation(): void
    {
        $cita = Cita::factory()->create();
        $slot = Slot::factory()->create(['medico_id' => $cita->medico_id, 'hora_inicio' => '10:00', 'hora_fin' => '10:15', 'estado' => 'reservado']);
        $payload = ['nuevo_slot_id' => $slot->id, 'motivo_reprogramacion' => 'Cambio solicitado'];
        $this->actingAs($this->usuario('recepcionista'))->postJson(route('citas.reprogramar.post', $cita), $payload)->assertUnprocessable()->assertJsonValidationErrors('aceptacion_paciente');
        $this->postJson(route('citas.reprogramar.post', $cita), $payload + ['aceptacion_paciente' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'slot_id' => $cita->slot_id, 'version' => 1]);
        $this->assertDatabaseHas('slots', ['id' => $cita->slot_id, 'estado' => 'reservado']);
        $this->assertDatabaseCount('historial_citas', 0);
    }

    public function test_block_postpones_pending_visits_and_preserves_started_attention(): void
    {
        Queue::fake([EnviarAviso::class]);
        $cita = Cita::factory()->create();
        $rec = $this->usuario('recepcionista');
        $slot = Slot::factory()->create(['medico_id' => $cita->medico_id, 'hora_inicio' => '10:00', 'hora_fin' => '10:15', 'estado' => 'reservado']);
        $iniciada = Cita::factory()->create(['slot_id' => $slot->id, 'estado' => 'en_atencion']);
        $this->actingAs($cita->medico->user)->post(route('bloqueos.store'), ['medico_id' => $cita->medico_id,
            'fecha_inicio' => '2026-11-07 08:00', 'fecha_fin' => '2026-11-07 12:00', 'tipo' => 'emergencia', 'motivo' => 'Suspensión de agenda'])->assertRedirect();
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'postergada']);
        $this->assertDatabaseHas('citas', ['id' => $iniciada->id, 'estado' => 'en_atencion']);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $rec->id, 'cita_id' => $cita->id, 'tipo' => 'postergada']);
        Queue::assertPushed(EnviarAviso::class);
        $bloqueo = BloqueoAgenda::sole();
        $this->delete(route('bloqueos.destroy', $bloqueo))->assertRedirect();
        $this->assertModelExists($bloqueo);
        $this->assertNotNull($bloqueo->fresh()->levantado_at);
        $this->assertDatabaseHas('slots', ['id' => $cita->slot_id, 'estado' => 'disponible']);
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'postergada']);
    }

    public function test_no_show_requires_end_of_slot_and_reception_role(): void
    {
        $cita = Cita::factory()->create(['fecha' => today()]);
        $this->travelTo('2026-11-06 09:14:59');
        $this->actingAs($this->usuario('admin'))->post(route('citas.inasistencia', $cita))->assertForbidden();
        $this->actingAs($this->usuario('recepcionista'))->post(route('citas.inasistencia', $cita))->assertUnprocessable();
        $this->assertDatabaseCount('historial_citas', 0);
        $this->travelTo('2026-11-06 09:15:00');
        $this->post(route('citas.inasistencia', $cita))->assertRedirect();
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'inasistencia']);
    }

    public function test_responsible_requires_linked_account_permission_and_current_validity(): void
    {
        $cita = Cita::factory()->create();
        $tutor = $this->usuario('paciente');
        $responsable = $cita->paciente->responsables()->create(['user_id' => $tutor->id, 'ci' => 'TUTOR', 'nombres' => 'Tutor', 'parentesco' => 'Tutor', 'telefono' => '70000000', 'activo' => true,
            'vigencia_inicio' => today(), 'fecha_limite_vigencia' => today(), 'puede_gestionar_citas' => true]);
        $this->actingAs($tutor)->get(route('historial.index', ['paciente_id' => $cita->paciente_id]))->assertOk()->assertSee($cita->codigo_ficha);
        $this->get(route('citas.nueva', ['paciente_id' => $cita->paciente_id]))->assertOk()->assertSee($cita->paciente->nombre_completo);
        $responsable->update(['puede_gestionar_citas' => false]);
        $this->get(route('citas.show', $cita))->assertForbidden();
        $responsable->update(['puede_gestionar_citas' => true, 'fecha_limite_vigencia' => today()->subDay()]);
        $this->get(route('citas.show', $cita))->assertForbidden();
    }

    public function test_administrative_history_omits_clinical_text_and_filters_dates(): void
    {
        $cita = Cita::factory()->create(['motivo_consulta' => 'MOTIVO CONFIDENCIAL', 'observaciones_atencion' => 'NOTA CONFIDENCIAL']);
        $this->actingAs($this->usuario('recepcionista'))->get(route('historial.index', ['paciente_id' => $cita->paciente_id, 'desde' => '2026-11-07', 'hasta' => '2026-11-07', 'estado' => 'programada']))->assertOk()->assertSee($cita->codigo_ficha)->assertDontSee('MOTIVO CONFIDENCIAL');
        $this->get(route('citas.show', $cita))->assertOk()->assertDontSee('MOTIVO CONFIDENCIAL')->assertDontSee('NOTA CONFIDENCIAL');
        $this->get(route('historial.index', ['paciente_id' => $cita->paciente_id, 'desde' => '2026-11-08']))->assertOk()->assertDontSee($cita->codigo_ficha);
    }

    public function test_booking_and_notification_are_idempotent_and_mail_has_no_clinical_content(): void
    {
        Mail::fake();
        $pac = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $pac->id]);
        $slot = Slot::factory()->create();
        $payload = ['paciente_id' => $paciente->id, 'slot_id' => $slot->id, 'medico_id' => $slot->medico_id, 'operacion_id' => (string) Str::uuid(), 'motivo_consulta' => 'CONFIDENCIAL'];
        $this->actingAs($pac)->post(route('citas.store'), $payload)->assertRedirect();
        $this->post(route('citas.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('citas', 1);
        $this->assertDatabaseCount('historial_citas', 1);
        $this->assertDatabaseCount('notificaciones', 1);
        $aviso = Notificacion::sole();
        (new EnviarAviso($aviso->id))->handle();
        (new EnviarAviso($aviso->id))->handle();
        Mail::assertSentCount(1);
        $this->assertStringNotContainsString('CONFIDENCIAL', (new AvisoCitaMail($aviso))->render());
        $this->get(route('avisos.index'))->assertOk()->assertSee('Cita programada');
        $this->actingAs($this->usuario('paciente'))->post(route('avisos.leer', $aviso))->assertForbidden();
        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'leido' => false]);
    }

    public function test_mail_failure_keeps_internal_notification_for_retry(): void
    {
        Queue::fake([EnviarAviso::class]);
        $pac = $this->usuario('paciente');
        $cita = Cita::factory()->create(['paciente_id' => Paciente::factory()->create(['user_id' => $pac->id])]);
        $this->actingAs($pac)->post(route('citas.cancelar', $cita), ['motivo_cancelacion' => 'Cambio de planes'])->assertRedirect();
        Queue::assertPushed(EnviarAviso::class);
        $aviso = Notificacion::sole();
        Mail::shouldReceive('to->send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        (new EnviarAviso($aviso->id))->handle();
        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'estado_envio' => 'fallido', 'intentos' => 1]);
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'cancelada']);
    }

    public function test_booking_from_derivation_checks_patient_and_destination(): void
    {
        $nota = AtencionMedica::factory()->create();
        $derivacion = Derivacion::factory()->create(['atencion_medica_id' => $nota->id]);
        $otroMedico = Medico::factory()->create();
        $slot = Slot::factory()->create(['medico_id' => $otroMedico->id, 'hora_inicio' => '11:00', 'hora_fin' => '11:15']);
        $payload = ['paciente_id' => $nota->historia->paciente_id, 'slot_id' => $slot->id, 'medico_id' => $slot->medico_id, 'derivacion_id' => $derivacion->id];
        $this->actingAs($this->usuario('recepcionista'))->post(route('citas.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseHas('derivaciones', ['id' => $derivacion->id, 'estado' => 'emitida']);
        $otroMedico->update(['especialidad_id' => $derivacion->especialidad_id]);
        $this->post(route('citas.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('citas', ['derivacion_id' => $derivacion->id, 'paciente_id' => $nota->historia->paciente_id]);
        $this->assertDatabaseHas('derivaciones', ['id' => $derivacion->id, 'estado' => 'agendada']);
    }

    public function test_doctor_cannot_create_their_own_patient_access_through_booking(): void
    {
        $medico = Medico::factory()->create();
        $paciente = Paciente::factory()->create();
        $slot = Slot::factory()->create(['medico_id' => $medico->id]);
        $this->actingAs($medico->user)->post(route('citas.store'), ['paciente_id' => $paciente->id, 'medico_id' => $medico->id, 'slot_id' => $slot->id])->assertForbidden();
        $this->get(route('historia.show', $paciente))->assertForbidden();
        $this->assertDatabaseCount('citas', 0);
    }

    public function test_patient_slot_api_does_not_serialize_other_patients(): void
    {
        $cita = Cita::factory()->create();
        $cita->paciente->update(['nombres' => 'PACIENTE CONFIDENCIAL']);
        $response = $this->actingAs($this->usuario('paciente'))->getJson(route('api.agenda.slots', ['medico_id' => $cita->medico_id, 'fecha' => $cita->fecha->toDateString()]));
        $response->assertOk()->assertDontSee('PACIENTE CONFIDENCIAL')->assertJsonMissingPath('slots.0.cita')->assertJsonMissingPath('slots.0.medico');
    }

    public function test_empty_or_invalid_clinical_note_does_not_open_an_expedient(): void
    {
        $cita = Cita::factory()->create(['estado' => 'en_atencion']);
        $this->actingAs($cita->medico->user)->postJson(route('historia.store', $cita->paciente), [])->assertUnprocessable()->assertJsonValidationErrors(['cita_id', 'version', 'operacion_id', 'motivo_consulta', 'diagnostico']);
        $this->assertDatabaseCount('historias_clinicas', 0);
        $this->assertDatabaseCount('atenciones_medicas', 0);
    }

    public function test_pending_mail_is_not_sent_after_responsible_authorization_is_revoked(): void
    {
        Mail::fake();
        Queue::fake([EnviarAviso::class]);
        $cita = Cita::factory()->create();
        $tutor = $this->usuario('paciente');
        $responsable = $cita->paciente->responsables()->create(['user_id' => $tutor->id, 'ci' => 'TUTOR', 'nombres' => 'Tutor', 'parentesco' => 'Tutor', 'telefono' => '70000000', 'activo' => true, 'fecha_limite_vigencia' => today()->addDay(), 'puede_gestionar_citas' => true]);
        $this->actingAs($tutor)->post(route('citas.cancelar', $cita), ['motivo_cancelacion' => 'Cambio de planes'])->assertRedirect();
        Queue::assertPushed(EnviarAviso::class);
        $responsable->update(['activo' => false]);
        $aviso = Notificacion::sole();
        (new EnviarAviso($aviso->id))->handle();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'estado_envio' => 'revocado']);
    }

    public function test_doctor_sees_only_own_appointments_in_history_and_empty_filters(): void
    {
        $medico1 = Medico::factory()->create();
        $medico2 = Medico::factory()->create();
        $paciente = Paciente::factory()->create();

        // El paciente tiene 1 cita con médico 1 y 1 cita con médico 2
        $cita1 = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico1->id,
            'codigo_ficha' => 'FCH-MED1-001',
            'fecha' => '2026-11-07',
        ]);
        $cita2 = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico2->id,
            'codigo_ficha' => 'FCH-MED2-002',
            'fecha' => '2026-11-07',
        ]);

        // 1. Médico 1 consulta el historial del paciente: solo ve su cita y NO la del Médico 2
        $response1 = $this->actingAs($medico1->user)->get(route('historial.index', ['paciente_id' => $paciente->id]));
        $response1->assertOk()
            ->assertSee('FCH-MED1-001')
            ->assertDontSee('FCH-MED2-002');

        // 2. Médico 2 consulta el historial del paciente: solo ve su cita y NO la del Médico 1
        $response2 = $this->actingAs($medico2->user)->get(route('historial.index', ['paciente_id' => $paciente->id]));
        $response2->assertOk()
            ->assertSee('FCH-MED2-002')
            ->assertDontSee('FCH-MED1-001');

        // 3. Cuando se filtra y no hay resultados, muestra estado vacío limpiamente sin errores
        $responseFiltroVacio = $this->actingAs($medico1->user)->get(route('historial.index', [
            'paciente_id' => $paciente->id,
            'desde' => '2026-11-20',
            'hasta' => '2026-11-25',
        ]));
        $responseFiltroVacio->assertOk()
            ->assertSee('No se encontraron citas')
            ->assertSee('No existen registros que coincidan con los filtros seleccionados')
            ->assertDontSee('FCH-MED1-001')
            ->assertDontSee('FCH-MED2-002');
    }
}
