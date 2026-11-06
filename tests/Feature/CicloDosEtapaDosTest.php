<?php

namespace Tests\Feature;

use App\Jobs\EnviarAviso;
use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P4\ListaEspera;
use App\Models\P4\OfertaCupo;
use App\Models\P5\ExamenLaboratorio;
use App\Models\P5\OrdenLaboratorio;
use App\Models\P5\ResultadoLaboratorio;
use App\Models\P7\Notificacion;
use App\Models\User;
use App\Services\RecuperacionCupoService;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CicloDosEtapaDosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-07 08:00:00');
        $this->seed(RolSeeder::class);
        Storage::fake('local');
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create([
            'rol_id' => Rol::where('slug', $rol)->firstOrFail()->id,
            'estado' => 'activo',
        ]);
    }

    public function test_doctor_can_emit_laboratory_order_with_multiple_exams(): void
    {
        $medico = Medico::factory()->create();
        $paciente = Paciente::factory()->create();
        Cita::factory()->create(['paciente_id' => $paciente->id, 'medico_id' => $medico->id]);

        $ex1 = ExamenLaboratorio::factory()->create(['nombre' => 'Hemograma']);
        $ex2 = ExamenLaboratorio::factory()->create(['nombre' => 'Glucosa']);

        $payload = [
            'examenes' => [$ex1->id, $ex2->id],
            'observaciones' => 'Pruebas prequirúrgicas',
            'operacion_id' => (string) Str::uuid(),
        ];

        $response = $this->actingAs($medico->user)->post(route('laboratorio.ordenes.store', $paciente), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('ordenes_laboratorio', [
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'estado' => 'emitida',
        ]);

        $orden = OrdenLaboratorio::sole();
        $this->assertCount(2, $orden->examenes);
        $this->assertDatabaseHas('ordenes_examenes', ['orden_laboratorio_id' => $orden->id, 'examen_laboratorio_id' => $ex1->id, 'estado' => 'pendiente']);
    }

    public function test_lab_user_can_capture_draft_result_and_upload_private_pdf(): void
    {
        $lab = $this->usuario('lab');
        $orden = OrdenLaboratorio::factory()->create();
        $ex = ExamenLaboratorio::factory()->create();
        $orden->examenes()->attach($ex->id, ['estado' => 'pendiente']);

        $file = UploadedFile::fake()->create('informe.pdf', 500, 'application/pdf');

        $payload = [
            'detalles' => [
                [
                    'examen_laboratorio_id' => $ex->id,
                    'parametro' => 'Hemoglobina',
                    'valor' => '14.5',
                    'unidad_medida' => 'g/dL',
                    'rango_referencia' => '12.0 - 16.0',
                    'interpretacion' => 'Normal',
                ],
            ],
            'archivo_pdf' => $file,
            'observaciones' => 'Muestra procesada sin hemólisis',
            'operacion_id' => (string) Str::uuid(),
        ];

        $this->actingAs($lab)->post(route('laboratorio.resultados.store', $orden), $payload)->assertRedirect();

        $this->assertDatabaseHas('resultados_laboratorio', [
            'orden_laboratorio_id' => $orden->id,
            'user_id' => $lab->id,
            'estado' => 'borrador',
            'version' => 1,
        ]);

        $resultado = ResultadoLaboratorio::sole();
        $this->assertNotNull($resultado->archivo_pdf_path);
        Storage::disk('local')->assertExists($resultado->archivo_pdf_path);
        $this->assertSame('en_proceso', $orden->fresh()->estado);
    }

    public function test_result_lifecycle_borrador_to_validado_to_publicado(): void
    {
        Queue::fake([EnviarAviso::class]);
        $lab = $this->usuario('lab');
        $pac = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $pac->id]);
        $orden = OrdenLaboratorio::factory()->create(['paciente_id' => $paciente->id]);
        $ex = ExamenLaboratorio::factory()->create();
        $orden->examenes()->attach($ex->id, ['estado' => 'pendiente']);

        $resultado = ResultadoLaboratorio::factory()->create([
            'orden_laboratorio_id' => $orden->id,
            'user_id' => $lab->id,
            'estado' => 'borrador',
        ]);
        $resultado->detalles()->create([
            'examen_laboratorio_id' => $ex->id,
            'parametro' => 'Glucosa',
            'valor' => '85',
        ]);

        // Intentar publicar desde borrador debe fallar
        $this->actingAs($lab)->post(route('laboratorio.resultados.publicar', $resultado))->assertUnprocessable();
        $this->assertSame('borrador', $resultado->fresh()->estado);

        // Validar
        $this->post(route('laboratorio.resultados.validar', $resultado))->assertRedirect();
        $this->assertSame('validado', $resultado->fresh()->estado);

        // Publicar
        $this->post(route('laboratorio.resultados.publicar', $resultado))->assertRedirect();
        $this->assertSame('publicado', $resultado->fresh()->estado);
        $this->assertSame('finalizada', $orden->fresh()->estado);

        // Notificación despachada
        $this->assertDatabaseHas('notificaciones', ['user_id' => $orden->paciente->user_id, 'tipo' => 'laboratorio']);
        Queue::assertPushed(EnviarAviso::class);
    }

    public function test_doctor_and_patient_cannot_view_draft_or_validated_results(): void
    {
        $lab = $this->usuario('lab');
        $pac = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $pac->id]);
        $orden = OrdenLaboratorio::factory()->create(['paciente_id' => $paciente->id]);

        $resultado = ResultadoLaboratorio::factory()->create([
            'orden_laboratorio_id' => $orden->id,
            'user_id' => $lab->id,
            'estado' => 'borrador',
        ]);

        $this->actingAs($pac)->get(route('laboratorio.resultados.show', $resultado))->assertForbidden();

        $resultado->update(['estado' => 'validado']);
        $this->get(route('laboratorio.resultados.show', $resultado))->assertForbidden();

        $resultado->update(['estado' => 'publicado']);
        $this->get(route('laboratorio.resultados.show', $resultado))->assertOk();
    }

    public function test_order_with_multiple_exams_only_finalizes_when_all_are_reported(): void
    {
        $lab = $this->usuario('lab');
        $orden = OrdenLaboratorio::factory()->create();
        $ex1 = ExamenLaboratorio::factory()->create();
        $ex2 = ExamenLaboratorio::factory()->create();
        $orden->examenes()->attach($ex1->id, ['estado' => 'pendiente']);
        $orden->examenes()->attach($ex2->id, ['estado' => 'pendiente']);

        // Publicar primer examen
        $res1 = ResultadoLaboratorio::factory()->create(['orden_laboratorio_id' => $orden->id, 'estado' => 'validado']);
        $res1->detalles()->create(['examen_laboratorio_id' => $ex1->id, 'parametro' => 'P1', 'valor' => 'V1']);
        $this->actingAs($lab)->post(route('laboratorio.resultados.publicar', $res1));

        $this->assertSame('en_proceso', $orden->fresh()->estado);

        // Publicar segundo examen
        $res2 = ResultadoLaboratorio::factory()->create(['orden_laboratorio_id' => $orden->id, 'estado' => 'validado']);
        $res2->detalles()->create(['examen_laboratorio_id' => $ex2->id, 'parametro' => 'P2', 'valor' => 'V2']);
        $this->post(route('laboratorio.resultados.publicar', $res2));

        $this->assertSame('finalizada', $orden->fresh()->estado);
    }

    public function test_waitlist_registration_stores_preferences(): void
    {
        $pac = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $pac->id]);
        $esp = Especialidad::factory()->create();

        $payload = [
            'paciente_id' => $paciente->id,
            'especialidad_id' => $esp->id,
            'fecha_desde' => '2026-11-08',
            'fecha_hasta' => '2026-11-15',
            'franja_horaria' => 'manana',
            'anticipacion_minima_horas' => 3,
        ];

        $this->actingAs($pac)->post(route('espera.store'), $payload)->assertRedirect(route('espera.index'));

        $this->assertDatabaseHas('listas_espera', [
            'paciente_id' => $paciente->id,
            'especialidad_id' => $esp->id,
            'franja_horaria' => 'manana',
            'estado' => 'activa',
        ]);
    }

    public function test_slot_liberation_creates_exclusive_10_minute_offer_for_earliest_candidate(): void
    {
        $esp = Especialidad::factory()->create();
        $medico = Medico::factory()->create(['especialidad_id' => $esp->id]);
        $slot = Slot::factory()->create(['medico_id' => $medico->id, 'fecha' => '2026-11-08', 'hora_inicio' => '10:00', 'hora_fin' => '10:15', 'estado' => 'disponible']);

        $candidato1 = ListaEspera::factory()->create([
            'especialidad_id' => $esp->id,
            'fecha_desde' => '2026-11-07',
            'fecha_hasta' => '2026-11-10',
            'fecha_inscripcion' => '2026-11-05',
        ]);

        $candidato2 = ListaEspera::factory()->create([
            'especialidad_id' => $esp->id,
            'fecha_desde' => '2026-11-07',
            'fecha_hasta' => '2026-11-10',
            'fecha_inscripcion' => '2026-11-06',
        ]);

        $servicio = app(RecuperacionCupoService::class);
        $oferta = $servicio->evaluarRecuperacion($slot);

        $this->assertNotNull($oferta);
        $this->assertSame($candidato1->id, $oferta->lista_espera_id);
        $this->assertSame('pendiente', $oferta->estado);
        $this->assertSame('2026-11-07 08:10:00', $oferta->fecha_vencimiento->format('Y-m-d H:i:s'));

        // Wizard ordinario no debe poder tomar este slot mientras esté la oferta activa
        $otroPaciente = Paciente::factory()->create();
        $this->actingAs($this->usuario('recepcionista'))->postJson(route('citas.store'), [
            'paciente_id' => $otroPaciente->id,
            'medico_id' => $medico->id,
            'slot_id' => $slot->id,
        ])->assertUnprocessable();
    }

    public function test_accepting_offer_with_prior_appointment_reschedules_same_appointment(): void
    {
        $pac = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $pac->id]);
        $esp = Especialidad::factory()->create();
        $medico = Medico::factory()->create(['especialidad_id' => $esp->id]);

        $slotOriginal = Slot::factory()->create(['medico_id' => $medico->id, 'fecha' => '2026-11-20', 'hora_inicio' => '11:00', 'hora_fin' => '11:15', 'estado' => 'reservado']);
        $citaPrevia = Cita::factory()->create(['paciente_id' => $paciente->id, 'medico_id' => $medico->id, 'slot_id' => $slotOriginal->id, 'fecha' => '2026-11-20', 'hora' => '11:00']);

        $slotAdelantado = Slot::factory()->create(['medico_id' => $medico->id, 'fecha' => '2026-11-09', 'hora_inicio' => '09:00', 'hora_fin' => '09:15', 'estado' => 'disponible']);

        $espera = ListaEspera::factory()->create([
            'paciente_id' => $paciente->id,
            'especialidad_id' => $esp->id,
            'cita_id' => $citaPrevia->id,
            'fecha_desde' => '2026-11-08',
            'fecha_hasta' => '2026-11-15',
        ]);

        $oferta = OfertaCupo::factory()->create([
            'lista_espera_id' => $espera->id,
            'slot_id' => $slotAdelantado->id,
            'estado' => 'pendiente',
            'fecha_vencimiento' => now()->addMinutes(10),
        ]);

        $this->actingAs($pac)->post(route('espera.ofertas.aceptar', $oferta))->assertRedirect();

        $this->assertDatabaseCount('citas', 1); // Misma cita, no se crea otra
        $citaActual = $citaPrevia->fresh();
        $this->assertSame($citaPrevia->id, $citaActual->id);
        $this->assertSame($slotAdelantado->id, $citaActual->slot_id);
        $this->assertTrue($citaActual->es_cupo_recuperado);
        $this->assertSame($oferta->id, $citaActual->oferta_cupo_id);
        $this->assertSame('aceptada', $oferta->fresh()->estado);
        $this->assertSame('atendida', $espera->fresh()->estado);
        $this->assertSame('disponible', $slotOriginal->fresh()->estado);
    }

    public function test_expired_offer_advances_to_next_candidate_and_rejects_late_acceptance(): void
    {
        $esp = Especialidad::factory()->create();
        $medico = Medico::factory()->create(['especialidad_id' => $esp->id]);
        $slot = Slot::factory()->create(['medico_id' => $medico->id, 'fecha' => '2026-11-10', 'hora_inicio' => '10:00', 'hora_fin' => '10:15', 'estado' => 'disponible']);

        $pac1 = $this->usuario('paciente');
        $paciente1 = Paciente::factory()->create(['user_id' => $pac1->id]);
        $c1 = ListaEspera::factory()->create(['paciente_id' => $paciente1->id, 'especialidad_id' => $esp->id, 'fecha_desde' => '2026-11-08', 'fecha_hasta' => '2026-11-15', 'fecha_inscripcion' => '2026-11-05']);
        $c2 = ListaEspera::factory()->create(['especialidad_id' => $esp->id, 'fecha_desde' => '2026-11-08', 'fecha_hasta' => '2026-11-15', 'fecha_inscripcion' => '2026-11-06']);

        $servicio = app(RecuperacionCupoService::class);
        $oferta1 = $servicio->evaluarRecuperacion($slot);
        $this->assertSame($c1->id, $oferta1->lista_espera_id);

        // Viajar 11 minutos al futuro para vencer la oferta
        $this->travel(11)->minutes();

        // Intentar aceptar tarde
        $this->actingAs($c1->paciente->user)->post(route('espera.ofertas.aceptar', $oferta1))->assertUnprocessable();

        // Correr proceso de vencimientos
        $procesadas = $servicio->procesarOfertasVencidas();
        $this->assertSame(1, $procesadas);
        $this->assertSame('vencida', $oferta1->fresh()->estado);

        // La segunda oferta debe haberse generado automáticamente para c2
        $oferta2 = OfertaCupo::where('lista_espera_id', $c2->id)->where('estado', 'pendiente')->first();
        $this->assertNotNull($oferta2);
        $this->assertSame($slot->id, $oferta2->slot_id);
    }
}
