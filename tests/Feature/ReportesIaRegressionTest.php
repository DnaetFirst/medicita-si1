<?php

namespace Tests\Feature;

use App\Models\P1\Rol;
use App\Models\P2\Medico;
use App\Models\P6\ReporteIa;
use App\Models\User;
use App\Services\AiReporteService;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportesIaRegressionTest extends TestCase
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
        return User::factory()->create(['rol_id' => Rol::where('slug', $rol)->firstOrFail()->id, 'estado' => 'activo']);
    }

    /** @return array<string, mixed> */
    private function respuesta(string $sql = 'SELECT medico_nombre, especialidad_nombre FROM vista_reporte_medicos;'): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode(['sql' => $sql, 'aclaracion' => ''])]]]]]];
    }

    /** @return array<string, mixed> */
    private function contenido(string $texto): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $texto]]]]]];
    }

    public function test_json_sql_and_trailing_semicolon_work_without_exposing_key_in_url(): void
    {
        Medico::factory()->create();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->respuesta())]);
        $this->actingAs($this->usuario())->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Lista los médicos y sus especialidades'])->assertOk()->assertJsonPath('ok', true)->assertJsonCount(1, 'filas');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'clave-prueba-no-exponer') && ! str_contains($request->url(), 'key=') && $request['generationConfig']['maxOutputTokens'] >= 2048);
        $this->assertDatabaseHas('reportes_ia', ['es_valido' => true, 'filas_retornadas' => 1]);
    }

    /** @return array<string, array{string}> */
    public static function consultasProhibidas(): array
    {
        return [
            'base clinical table' => ['SELECT motivo_consulta FROM citas'],
            'unknown table and allowed word' => ["SELECT 'vista_reporte_citas' FROM failed_jobs"],
            'system function' => ["SELECT pg_read_file('/etc/passwd') FROM vista_reporte_citas"],
            'nested query' => ['SELECT (SELECT token FROM password_reset_tokens) FROM vista_reporte_citas'],
            'union' => ['SELECT medico_nombre FROM vista_reporte_medicos UNION SELECT password FROM users'],
            'join' => ['SELECT m.medico_nombre FROM vista_reporte_medicos m JOIN users u ON 1=1'],
            'multiple statements' => ['SELECT * FROM vista_reporte_citas; DELETE FROM citas'],
            'unknown column' => ['SELECT password FROM vista_reporte_medicos'],
            'recursive query' => ['WITH RECURSIVE x AS (SELECT 1) SELECT * FROM vista_reporte_citas'],
            'negative limit' => ['SELECT * FROM vista_reporte_citas LIMIT -1'],
        ];
    }

    #[DataProvider('consultasProhibidas')]
    public function test_sql_parser_rejects_unauthorized_structures(string $sql): void
    {
        $resultado = app(AiReporteService::class)->validarSql($sql);
        $this->assertFalse($resultado['es_valido']);
        $this->assertNotEmpty($resultado['motivo']);
    }

    public function test_aggregates_percentages_case_filters_and_limit_are_supported(): void
    {
        $sql = "SELECT especialidad_nombre, ROUND(100.0 * SUM(CASE WHEN estado = 'atendida' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 1) AS porcentaje FROM vista_reporte_citas WHERE DATE(fecha_cita) BETWEEN '2026-11-01' AND '2026-11-07' GROUP BY especialidad_nombre ORDER BY porcentaje DESC LIMIT 500";
        $servicio = app(AiReporteService::class);
        $this->assertTrue($servicio->validarSql($sql)['es_valido']);
        $this->assertStringEndsWith('LIMIT 100', $servicio->asegurarLimite($sql));
    }

    public function test_free_quota_error_is_safe_and_is_audited_without_provider_body(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => str_repeat('clave-prueba-no-exponer', 80)]], 429)]);
        $response = $this->actingAs($this->usuario())->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Lista los médicos']);
        $response->assertOk()->assertJsonPath('ok', false)->assertDontSee('clave-prueba-no-exponer');
        $this->assertStringContainsString('cuota gratuita', $response->json('mensaje'));
        $this->assertLessThanOrEqual(250, strlen(ReporteIa::sole()->motivo_rechazo));
        Http::assertSentCount(1);
    }

    public function test_invalid_sql_is_regenerated_once_without_executing_it(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()->push($this->respuesta('SELECT password FROM users'))->push($this->respuesta('SELECT COUNT(*) AS total FROM vista_reporte_medicos'))]);
        $this->actingAs($this->usuario())->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Cuántos médicos hay'])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('filas.0.total', 0);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('reportes_ia', 1);
    }

    public function test_followup_includes_previous_question_and_sql_without_rows(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->respuesta())]);
        $this->actingAs($this->usuario())->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Lista los médicos'])->assertOk();
        $this->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Y solo las especialidades'])->assertOk();
        Http::assertSent(fn (Request $r): bool => str_contains($r['contents'][0]['parts'][0]['text'], 'Lista los médicos') && str_contains($r['contents'][0]['parts'][0]['text'], 'Y solo las especialidades') && str_contains($r['contents'][0]['parts'][0]['text'], 'SELECT'));
    }

    public function test_http_endpoint_rejects_manual_sql_and_unauthorized_roles(): void
    {
        Http::fake();
        $this->actingAs($this->usuario())->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Consulta', 'sql_manual' => 'SELECT * FROM citas'])->assertUnprocessable()->assertJsonValidationErrors('sql_manual');
        $this->actingAs($this->usuario('paciente'))->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Consulta'])->assertForbidden();
        $this->postJson(route('reportes.ia.transcribir'), [])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_truncated_response_and_missing_key_do_not_execute_or_crash_audit(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => 'SELECT']]]]]])]);
        $this->actingAs($this->usuario())->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Lista médicos'])->assertOk()->assertJsonPath('ok', false);
        config(['services.gemini.key' => null]);
        $this->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Lista médicos'])->assertOk()->assertJsonPath('ok', false);
        $this->assertDatabaseCount('reportes_ia', 2);
        Http::assertSentCount(1);
    }

    public function test_ambiguous_question_returns_clarification_instead_of_executing_sql(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->contenido(json_encode(['sql' => '', 'aclaracion' => '¿Qué período deseas consultar?'])))]);
        $this->actingAs($this->usuario())->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Dame el mejor'])->assertOk()->assertJsonPath('ok', false)->assertJsonPath('mensaje', '¿Qué período deseas consultar?');
        Http::assertSentCount(1);
    }

    public function test_audio_is_transcribed_and_never_executes_a_report_automatically(): void
    {
        $contenido = 'RIFF'.pack('V', 2036).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 2000).str_repeat(pack('v', 1000), 1000);
        $audio = UploadedFile::fake()->createWithContent('pregunta.wav', $contenido);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->contenido('Cuántas citas atendidas hubo hoy'))]);
        $this->actingAs($this->usuario())->postJson(route('reportes.ia.transcribir'), ['audio' => $audio])->assertOk()->assertJsonPath('texto', 'Cuántas citas atendidas hubo hoy');
        Http::assertSent(fn (Request $r): bool => $r['contents'][0]['parts'][0]['inlineData']['mimeType'] === 'audio/x-wav' || $r['contents'][0]['parts'][0]['inlineData']['mimeType'] === 'audio/wav');
        $this->assertDatabaseCount('reportes_ia', 0);
    }

    public function test_ai_exports_keep_snapshot_after_source_changes_and_enforce_owner(): void
    {
        $usuario = $this->usuario();
        $medico = Medico::factory()->create();
        $medico->user->update(['name' => 'Nombre original']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->respuesta())]);
        $respuesta = $this->actingAs($usuario)->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Lista médicos'])->assertOk()->assertJsonPath('ok', true);
        $id = $respuesta->json('reporte_id');
        $medico->user->update(['name' => 'Nombre modificado']);
        $this->get(route('reportes.ia.csv', $id))->assertOk()->assertSee('Nombre original')->assertDontSee('Nombre modificado');
        $this->get(route('reportes.ia.pdf', $id))->assertOk()->assertSee('Nombre original')->assertDontSee('Nombre modificado');
        $this->actingAs($this->usuario('recepcionista'))->get(route('reportes.ia.csv', $id))->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_audio_without_speech_returns_actionable_error(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->contenido(json_encode(['hay_voz' => false, 'texto' => ''])))]);
        try {
            app(AiReporteService::class)->transcribirAudio('audio de prueba', 'audio/wav');
            $this->fail('El silencio no debe convertirse en una consulta.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No se reconoció voz', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_clarification_followup_keeps_original_question(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()->push($this->contenido(json_encode(['sql' => '', 'aclaracion' => '¿Qué período deseas consultar?'])))->push($this->respuesta('SELECT COUNT(*) AS total FROM vista_reporte_citas'))]);
        $this->actingAs($this->usuario())->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Cuántas citas atendidas hubo'])->assertOk()->assertJsonPath('necesita_aclaracion', true);
        $this->postJson(route('reportes.ia.consultar'), ['pregunta' => 'Este mes'])->assertOk()->assertJsonPath('ok', true);
        Http::assertSent(fn (Request $r): bool => str_contains($r['contents'][0]['parts'][0]['text'], 'Cuántas citas atendidas hubo') && str_contains($r['contents'][0]['parts'][0]['text'], 'Este mes'));
    }

    public function test_silent_wav_is_rejected_without_spending_provider_quota(): void
    {
        Http::fake();
        $wav = 'RIFF'.pack('V', 2036).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 2000).str_repeat(chr(0), 2000);
        try {
            app(AiReporteService::class)->transcribirAudio($wav, 'audio/wav');
            $this->fail('El silencio no debe enviarse al proveedor.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No se detectó sonido', $exception->getMessage());
        }
        Http::assertNothingSent();
    }
}
