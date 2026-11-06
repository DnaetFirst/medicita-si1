<?php

namespace Tests\Feature;

use App\Jobs\EnviarAviso;
use App\Models\P1\Rol;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P4\HistorialCita;
use App\Models\P4\ListaEspera;
use App\Models\P4\OfertaCupo;
use App\Models\P5\ExamenLaboratorio;
use App\Models\P5\OrdenLaboratorio;
use App\Models\P5\ResultadoLaboratorio;
use App\Models\User;
use App\Services\AnalizadorSqlReporte;
use App\Services\ReporteIndicadoresService;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CasosUsoRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->travelTo('2026-11-07 08:00:00');
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['rol_id' => Rol::where('slug', $rol)->firstOrFail()->id, 'estado' => 'activo']);
    }

    public function test_kpis_count_completed_visits_and_same_id_reschedules_correctly(): void
    {
        Cita::factory()->create(['estado' => 'en_atencion']);
        $cita = Cita::factory()->create(['estado' => 'atendida']);
        HistorialCita::factory()->create(['cita_id' => $cita->id, 'operacion' => 'REPROGRAMAR']);
        $kpis = app(ReporteIndicadoresService::class)->calcularKpis();
        $this->assertSame(1, $kpis['completadas']);
        $this->assertSame(1, $kpis['reprogramadas']);
        $this->assertSame(50.0, $kpis['tasa_asistencia']);
    }

    public function test_all_analytical_views_expose_only_the_declared_schema(): void
    {
        foreach (AnalizadorSqlReporte::VISTAS as $vista => $columnas) {
            $this->assertSame($columnas, array_column(Schema::getColumns($vista), 'name'), $vista);
            DB::table($vista)->limit(1)->get();
        }
    }

    public function test_result_refuses_an_exam_outside_its_order_without_partial_writes(): void
    {
        Storage::fake('local');
        $orden = OrdenLaboratorio::factory()->create();
        $ajeno = ExamenLaboratorio::factory()->create();
        $this->actingAs($this->usuario('lab'))->post(route('laboratorio.resultados.store', $orden), ['detalles' => [['examen_laboratorio_id' => $ajeno->id, 'parametro' => 'Prueba', 'valor' => '10']]])->assertUnprocessable();
        $this->assertDatabaseCount('resultados_laboratorio', 0);
        $this->assertDatabaseCount('detalles_resultado', 0);
        Storage::disk('local')->assertDirectoryEmpty('laboratorio');
    }

    public function test_pdf_survives_ephemeral_disk_loss_and_draft_retries_are_idempotent(): void
    {
        Storage::fake('local');
        $lab = $this->usuario('lab');
        $orden = OrdenLaboratorio::factory()->create();
        $examen = ExamenLaboratorio::factory()->create();
        $orden->examenes()->attach($examen, ['estado' => 'pendiente']);
        $pdf = "%PDF-1.4\nDocumento sintético de prueba\n%%EOF";
        $datos = ['operacion_id' => (string) Str::uuid(), 'archivo_pdf' => UploadedFile::fake()->createWithContent('informe.pdf', $pdf), 'detalles' => [['examen_laboratorio_id' => $examen->id, 'parametro' => 'Prueba', 'valor' => '10']]];
        $this->actingAs($lab)->post(route('laboratorio.resultados.store', $orden), $datos)->assertRedirect();
        $this->post(route('laboratorio.resultados.store', $orden), $datos)->assertRedirect();
        $this->assertDatabaseCount('resultados_laboratorio', 1);
        $resultado = ResultadoLaboratorio::sole();
        $this->assertStringNotContainsString('Documento', DB::table('resultados_laboratorio')->value('archivo_pdf_contenido'));
        Storage::disk('local')->delete($resultado->archivo_pdf_path);
        $this->get(route('laboratorio.resultados.descargar', $resultado))->assertOk()->assertDownload();
        $this->assertSame($pdf, $this->get(route('laboratorio.resultados.descargar', $resultado))->streamedContent());
        $this->actingAs($this->usuario('paciente'))->get(route('laboratorio.resultados.descargar', $resultado))->assertForbidden();
    }

    public function test_offer_acceptance_revalidates_occupation_and_does_not_overbook(): void
    {
        $user = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $user->id]);
        $ocupada = Cita::factory()->create();
        $lista = ListaEspera::factory()->create(['paciente_id' => $paciente->id, 'especialidad_id' => $ocupada->medico->especialidad_id]);
        $oferta = OfertaCupo::factory()->create(['lista_espera_id' => $lista->id, 'slot_id' => $ocupada->slot_id]);
        $this->actingAs($user)->post(route('espera.ofertas.aceptar', $oferta))->assertUnprocessable();
        $this->assertDatabaseCount('citas', 1);
        $this->assertDatabaseHas('ofertas_cupo', ['id' => $oferta->id, 'estado' => 'pendiente']);
    }

    public function test_offer_acceptance_retry_returns_the_original_appointment(): void
    {
        Queue::fake([EnviarAviso::class]);
        $user = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $user->id]);
        $slot = Slot::factory()->create();
        $lista = ListaEspera::factory()->create(['paciente_id' => $paciente->id, 'especialidad_id' => $slot->medico->especialidad_id]);
        $oferta = OfertaCupo::factory()->create(['lista_espera_id' => $lista->id, 'slot_id' => $slot->id]);
        $this->actingAs($user)->post(route('espera.ofertas.aceptar', $oferta))->assertRedirect();
        $this->post(route('espera.ofertas.aceptar', $oferta))->assertRedirect();
        $this->assertDatabaseCount('citas', 1);
        $this->assertDatabaseCount('historial_citas', 1);
        Queue::assertPushed(EnviarAviso::class, 1);
    }

    public function test_invalid_report_dates_are_rejected_on_screen_and_exports(): void
    {
        $this->actingAs($this->usuario('recepcionista'));
        foreach (['reportes.index', 'reportes.csv', 'reportes.pdf'] as $ruta) {
            $this->getJson(route($ruta, ['fecha_inicio' => '2026-11-08', 'fecha_fin' => '2026-11-01']))->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
        }
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        $paciente = Paciente::factory()->create(['nombres' => '=HYPERLINK("https://example.com")', 'apellidos' => 'Prueba']);
        Cita::factory()->create(['paciente_id' => $paciente->id]);
        $csv = app(ReporteIndicadoresService::class)->generarCsv();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_order_page_does_not_expose_draft_results_to_patient_or_doctor(): void
    {
        $usuario = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $usuario->id]);
        $orden = OrdenLaboratorio::factory()->create(['paciente_id' => $paciente->id]);
        Cita::factory()->create(['paciente_id' => $paciente->id, 'medico_id' => $orden->medico_id]);
        $resultado = ResultadoLaboratorio::factory()->create(['orden_laboratorio_id' => $orden->id, 'estado' => 'borrador']);
        $examen = ExamenLaboratorio::factory()->create();
        $resultado->detalles()->create(['examen_laboratorio_id' => $examen->id, 'parametro' => 'VALOR NO PUBLICADO', 'valor' => '999']);
        $this->actingAs($usuario)->get(route('laboratorio.ordenes.show', $orden))->assertOk()->assertDontSee('VALOR NO PUBLICADO');
        $this->actingAs($orden->medico->user)->get(route('laboratorio.ordenes.show', $orden))->assertOk()->assertDontSee('VALOR NO PUBLICADO');
        $this->actingAs($this->usuario('admin'))->get(route('laboratorio.resultados.show', $resultado))->assertForbidden();
        $this->get(route('laboratorio.ordenes.show', $orden))->assertForbidden();
    }

    public function test_responsible_with_result_permission_can_view_published_order_without_booking_permission(): void
    {
        $tutor = $this->usuario('paciente');
        $orden = OrdenLaboratorio::factory()->create();
        $orden->paciente->responsables()->create(['user_id' => $tutor->id, 'ci' => 'TUTOR', 'nombres' => 'Tutor', 'parentesco' => 'Tutor', 'telefono' => '70000000', 'activo' => true, 'fecha_limite_vigencia' => today()->addDay(), 'puede_gestionar_citas' => false, 'puede_ver_resultados' => true]);
        ResultadoLaboratorio::factory()->create(['orden_laboratorio_id' => $orden->id, 'estado' => 'publicado']);
        $this->actingAs($tutor)->get(route('laboratorio.ordenes.show', $orden))->assertOk();
        $this->get(route('historia.show', $orden->paciente))->assertForbidden();
    }

    public function test_withdrawing_waitlist_annuls_pending_offer_and_preserves_prior_visit(): void
    {
        $user = $this->usuario('paciente');
        $paciente = Paciente::factory()->create(['user_id' => $user->id]);
        $cita = Cita::factory()->create(['paciente_id' => $paciente->id]);
        $slot = Slot::factory()->create(['medico_id' => $cita->medico_id, 'hora_inicio' => '11:00', 'hora_fin' => '11:15']);
        $lista = ListaEspera::factory()->create(['paciente_id' => $paciente->id, 'cita_id' => $cita->id, 'especialidad_id' => $cita->medico->especialidad_id]);
        $oferta = OfertaCupo::factory()->create(['lista_espera_id' => $lista->id, 'slot_id' => $slot->id]);
        $this->actingAs($user)->delete(route('espera.destroy', $lista))->assertRedirect();
        $this->post(route('espera.ofertas.aceptar', $oferta))->assertUnprocessable();
        $this->assertDatabaseHas('ofertas_cupo', ['id' => $oferta->id, 'estado' => 'anulada']);
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'slot_id' => $cita->slot_id, 'estado' => 'programada']);
    }

    public function test_repeated_publication_does_not_duplicate_notifications_or_audit(): void
    {
        Queue::fake([EnviarAviso::class]);
        $paciente = Paciente::factory()->create(['user_id' => $this->usuario('paciente')->id]);
        $orden = OrdenLaboratorio::factory()->create(['paciente_id' => $paciente->id]);
        $examen = ExamenLaboratorio::factory()->create();
        $orden->examenes()->attach($examen, ['estado' => 'pendiente']);
        $resultado = ResultadoLaboratorio::factory()->create(['orden_laboratorio_id' => $orden->id, 'estado' => 'validado']);
        $resultado->detalles()->create(['examen_laboratorio_id' => $examen->id, 'parametro' => 'Prueba', 'valor' => '10']);
        $this->actingAs($this->usuario('lab'))->post(route('laboratorio.resultados.publicar', $resultado))->assertRedirect();
        $avisos = DB::table('notificaciones')->count();
        $this->post(route('laboratorio.resultados.publicar', $resultado))->assertRedirect();
        $this->assertDatabaseCount('notificaciones', $avisos);
        $this->assertSame(1, DB::table('bitacoras')->where('accion', 'PUBLICAR_RESULTADO_LABORATORIO')->count());
        Queue::assertPushed(EnviarAviso::class);
    }
}
