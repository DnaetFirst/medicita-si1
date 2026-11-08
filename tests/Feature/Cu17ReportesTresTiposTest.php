<?php

namespace Tests\Feature;

use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Cu17ReportesTresTiposTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->travelTo('2026-11-07 10:00:00');
        config(['services.gemini.key' => 'clave-prueba-no-exponer', 'services.gemini.model' => 'gemini-2.5-flash']);
        Http::preventStrayRequests();
    }

    private function usuario(string $rol = 'admin'): User
    {
        return User::factory()->create([
            'rol_id' => Rol::where('slug', $rol)->firstOrFail()->id,
            'estado' => 'activo',
        ]);
    }

    private function crearDatosPrueba(): Cita
    {
        $especialidad = Especialidad::factory()->create(['nombre' => 'Cardiología']);
        $medicoUser = User::factory()->create(['name' => 'Dr. Carlos Mendoza']);
        $medico = Medico::factory()->create([
            'user_id' => $medicoUser->id,
            'especialidad_id' => $especialidad->id,
            'estado' => 'activo',
        ]);
        $pacienteUser = User::factory()->create(['name' => 'Juan Perez']);
        $paciente = Paciente::factory()->create([
            'user_id' => $pacienteUser->id,
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'ci' => '1234567',
        ]);

        return Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'fecha' => '2026-11-07',
            'hora' => '10:00:00',
            'estado' => 'atendida',
            'codigo_ficha' => 'FIC-TEST-001',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TIPO 1: Reportes por Filtros (CSV estructurado y PDF binario)
    // ──────────────────────────────────────────────────────────────────

    public function test_tipo_1_exportar_csv_incluye_encabezados_y_nombre_descriptivo(): void
    {
        $this->crearDatosPrueba();
        $user = $this->usuario('admin');

        $response = $this->actingAs($user)->get(route('reportes.csv', [
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-07',
            'estado' => 'atendida',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('reporte_', $disposition);
        $this->assertStringContainsString('.csv', $disposition);

        $contenido = $response->getContent();
        $this->assertStringContainsString('Codigo Ficha', $contenido);
        $this->assertStringContainsString('FIC-TEST-001', $contenido);
        $this->assertStringContainsString('Cardiología', $contenido);
    }

    public function test_tipo_1_exportar_pdf_binario_genera_pdf_1_4_con_kpis_y_citas(): void
    {
        $this->crearDatosPrueba();
        $user = $this->usuario('recepcionista');

        $response = $this->actingAs($user)->get(route('reportes.pdf.binario', [
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-07',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('.pdf', $disposition);

        $pdf = $response->getContent();
        // Verificar encabezado ISO PDF 1.4
        $this->assertStringStartsWith("%PDF-1.4\n", $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    // ──────────────────────────────────────────────────────────────────
    // TIPO 2: Constructor Visual de Reportes
    // ──────────────────────────────────────────────────────────────────

    public function test_tipo_2_constructor_preview_devuelve_columnas_y_filas_seleccionadas(): void
    {
        $this->crearDatosPrueba();
        $user = $this->usuario('admin');

        $response = $this->actingAs($user)->postJson(route('reportes.constructor.preview'), [
            'modo' => 'columnas',
            'columnas' => ['codigo_ficha', 'especialidad', 'medico', 'paciente', 'ci', 'estado'],
            'agrupacion' => 'ninguna',
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-07',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonStructure([
            'ok', 'titulo', 'agrupacion_label', 'columnas', 'filas', 'total', 'nombre_archivo',
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
        $this->assertContains('Código de Ficha', $response->json('columnas'));
        $this->assertContains('Paciente', $response->json('columnas'));
        $this->assertContains('CI Paciente', $response->json('columnas'));
        $this->assertContains('Estado', $response->json('columnas'));

        $filas = $response->json('filas');
        $this->assertNotEmpty($filas);
        $this->assertEquals('FIC-TEST-001', $filas[0]['Código de Ficha']);
        $this->assertEquals('1234567', $filas[0]['CI Paciente']);
    }

    public function test_tipo_2_constructor_modo_columnas_con_agrupacion_ordena_y_respeta_columnas(): void
    {
        $this->crearDatosPrueba();
        $user = $this->usuario('recepcionista');

        $response = $this->actingAs($user)->postJson(route('reportes.constructor.preview'), [
            'modo' => 'columnas',
            'columnas' => ['paciente', 'medico', 'estado'],
            'agrupacion' => 'especialidad',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        // Debe incluir Especialidad al inicio por la agrupación y respetar todas las columnas
        $this->assertContains('Especialidad', $response->json('columnas'));
        $this->assertContains('Paciente', $response->json('columnas'));
        $this->assertContains('Médico', $response->json('columnas'));
        $this->assertContains('Estado', $response->json('columnas'));
    }

    public function test_tipo_2_constructor_preview_con_agrupacion_calcula_metricas(): void
    {
        $this->crearDatosPrueba();
        $user = $this->usuario('recepcionista');

        $response = $this->actingAs($user)->postJson(route('reportes.constructor.preview'), [
            'modo' => 'metricas',
            'agrupacion' => 'especialidad',
            'metricas' => ['total', 'tasa_asistencia', 'cancelaciones'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertContains('Especialidad', $response->json('columnas'));
        $this->assertContains('Total de citas', $response->json('columnas'));
        $this->assertContains('Tasa de asistencia (%)', $response->json('columnas'));

        $filas = $response->json('filas');
        $this->assertNotEmpty($filas);
        $this->assertEquals('Cardiología', $filas[0]['Especialidad']);
        $this->assertEquals(1, $filas[0]['Total de citas']);
    }

    public function test_tipo_2_constructor_exportar_csv_genera_archivo_con_metadatos(): void
    {
        $this->crearDatosPrueba();
        $user = $this->usuario('admin');

        $response = $this->actingAs($user)->post(route('reportes.constructor.csv'), [
            'columnas' => ['fecha', 'medico', 'paciente', 'estado'],
            'agrupacion' => 'ninguna',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('reporte_', $disposition);
        $this->assertStringContainsString('.csv', $disposition);

        $contenido = $response->getContent();
        $this->assertStringContainsString('Reporte', $contenido);
        $this->assertStringContainsString('Total registros', $contenido);
    }

    public function test_tipo_2_constructor_exportar_pdf_genera_documento_personalizado(): void
    {
        $this->crearDatosPrueba();
        $user = $this->usuario('admin');

        $response = $this->actingAs($user)->post(route('reportes.constructor.pdf'), [
            'agrupacion' => 'medico',
            'metricas' => ['total', 'tasa_asistencia'],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('.pdf', $disposition);

        $pdf = $response->getContent();
        $this->assertStringStartsWith("%PDF-1.4\n", $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    // ──────────────────────────────────────────────────────────────────
    // TIPO 3: Asistente IA / Voz (Título legible y exportación binaria)
    // ──────────────────────────────────────────────────────────────────

    public function test_tipo_3_ia_consulta_retorna_titulo_reporte_y_urls_de_exportacion(): void
    {
        Medico::factory()->create();
        $user = $this->usuario('admin');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'sql' => 'SELECT medico_nombre, especialidad_nombre FROM vista_reporte_medicos;',
                            'aclaracion' => '',
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        $response = $this->actingAs($user)->postJson(route('reportes.ia.consultar'), [
            'pregunta' => '¿Cuáles son los médicos activos en la clínica?',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertNotEmpty($response->json('titulo_reporte'));
        $this->assertStringContainsString('Reporte IA', $response->json('titulo_reporte'));
        $this->assertNotNull($response->json('exportar_csv_url'));
        $this->assertNotNull($response->json('exportar_pdf_url'));
    }

    public function test_tipo_3_ia_exportar_pdf_binario_descarga_archivo_con_formato_oficial(): void
    {
        $user = $this->usuario('admin');
        Medico::factory()->create();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'sql' => 'SELECT medico_nombre, especialidad_nombre FROM vista_reporte_medicos;',
                            'aclaracion' => '',
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        $consulta = $this->actingAs($user)->postJson(route('reportes.ia.consultar'), [
            'pregunta' => 'Listar médicos',
        ]);
        $reporteId = $consulta->json('reporte_id');

        $response = $this->actingAs($user)->get(route('reportes.ia.pdf.binario', $reporteId));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('.pdf', $disposition);

        $pdf = $response->getContent();
        $this->assertStringStartsWith("%PDF-1.4\n", $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }
}
