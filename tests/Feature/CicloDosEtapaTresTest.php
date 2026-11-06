<?php

namespace Tests\Feature;

use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P6\ReporteIa;
use App\Models\User;
use App\Services\AiReporteService;
use App\Services\ReporteIndicadoresService;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CicloDosEtapaTresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-07 08:00:00');
        $this->seed(RolSeeder::class);
    }

    private function crearUsuario(string $rolSlug): User
    {
        return User::factory()->create([
            'rol_id' => Rol::where('slug', $rolSlug)->firstOrFail()->id,
            'estado' => 'activo',
        ]);
    }

    public function test_indicadores_service_calculates_correct_kpis(): void
    {
        $medico = Medico::factory()->create();
        $paciente = Paciente::factory()->create();

        // 1 atendida
        Cita::factory()->create([
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'estado' => 'atendida',
            'fecha' => '2026-11-07',
            'hora' => '09:00:00',
        ]);

        // 1 cancelada recuperada
        Cita::factory()->create([
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'estado' => 'cancelada',
            'es_cupo_recuperado' => true,
            'fecha' => '2026-11-07',
            'hora' => '10:00:00',
        ]);

        // 1 inasistencia
        Cita::factory()->create([
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'estado' => 'inasistencia',
            'fecha' => '2026-11-07',
            'hora' => '11:00:00',
        ]);

        // 1 programada
        Cita::factory()->create([
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'estado' => 'programada',
            'fecha' => '2026-11-07',
            'hora' => '12:00:00',
        ]);

        $service = app(ReporteIndicadoresService::class);
        $kpis = $service->calcularKpis(['fecha_inicio' => '2026-11-07', 'fecha_fin' => '2026-11-07']);

        $this->assertEquals(4, $kpis['total_citas']);
        $this->assertEquals(1, $kpis['completadas']);
        $this->assertEquals(1, $kpis['canceladas']);
        $this->assertEquals(1, $kpis['inasistencias']);
        $this->assertEquals(1, $kpis['cupos_recuperados']);
        $this->assertEquals(25.0, $kpis['tasa_asistencia']);
        $this->assertEquals(25.0, $kpis['tasa_cancelacion']);
        $this->assertEquals(25.0, $kpis['tasa_no_show']);
        $this->assertEquals(100.0, $kpis['tasa_recuperacion']);
    }

    public function test_rbac_access_control_to_reports(): void
    {
        $admin = $this->crearUsuario('admin');
        $recepcionista = $this->crearUsuario('recepcionista');
        $pacienteUser = $this->crearUsuario('paciente');
        $labUser = $this->crearUsuario('lab');

        // Admin & Recepcionista permitidos
        $this->actingAs($admin)->get(route('reportes.index'))->assertOk();
        $this->actingAs($recepcionista)->get(route('reportes.index'))->assertOk();

        // Paciente y Laboratorio denegados (403)
        $this->actingAs($pacienteUser)->get(route('reportes.index'))->assertForbidden();
        $this->actingAs($labUser)->get(route('reportes.index'))->assertForbidden();

        // No autenticado redirige a login
        auth()->logout();
        $this->get(route('reportes.index'))->assertRedirect(route('login'));
    }

    public function test_csv_export_returns_structured_data_and_headers(): void
    {
        $admin = $this->crearUsuario('admin');
        $medico = Medico::factory()->create();
        $paciente = Paciente::factory()->create();

        Cita::factory()->create([
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'codigo_ficha' => 'FCH-REP-TEST-001',
            'estado' => 'confirmada',
        ]);

        $response = $this->actingAs($admin)->get(route('reportes.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="reporte_citas_', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('FCH-REP-TEST-001', $response->getContent());
    }

    public function test_print_pdf_view_renders_correctly(): void
    {
        $admin = $this->crearUsuario('admin');
        $medico = Medico::factory()->create();
        $paciente = Paciente::factory()->create();

        Cita::factory()->create([
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'codigo_ficha' => 'FCH-PRINT-001',
        ]);

        $response = $this->actingAs($admin)->get(route('reportes.pdf'));

        $response->assertOk();
        $response->assertSee('MediCita Pro');
        $response->assertSee('FCH-PRINT-001');
        $response->assertSee('Total Citas Registradas');
    }

    public function test_sql_ast_validator_permits_safe_queries_and_enforces_limits(): void
    {
        $service = app(AiReporteService::class);

        $valido = $service->validarSql('SELECT fecha_cita, medico_nombre, estado FROM vista_reporte_citas WHERE estado = \'cancelada\'');
        $this->assertTrue($valido['es_valido']);
        $this->assertNull($valido['motivo']);

        $conLimite = $service->asegurarLimite('SELECT * FROM vista_reporte_citas', 100);
        $this->assertStringContainsString('LIMIT 100', $conLimite);

        $conLimiteExcesivo = $service->asegurarLimite('SELECT * FROM vista_reporte_citas LIMIT 500', 100);
        $this->assertStringContainsString('LIMIT 100', $conLimiteExcesivo);
    }

    public function test_sql_ast_validator_rejects_destructive_and_insecure_commands(): void
    {
        $service = app(AiReporteService::class);

        // DROP TABLE
        $r1 = $service->validarSql('DROP TABLE users');
        $this->assertFalse($r1['es_valido']);

        // DELETE
        $r2 = $service->validarSql('DELETE FROM citas WHERE id = 1');
        $this->assertFalse($r2['es_valido']);

        // UPDATE
        $r3 = $service->validarSql('UPDATE citas SET estado = \'atendida\'');
        $this->assertFalse($r3['es_valido']);

        // INSERT
        $r4 = $service->validarSql('INSERT INTO citas (codigo_ficha) VALUES (\'TEST\')');
        $this->assertFalse($r4['es_valido']);

        // Múltiples sentencias (inyección por semicolon)
        $r5 = $service->validarSql('SELECT * FROM vista_reporte_citas; DROP TABLE citas');
        $this->assertFalse($r5['es_valido']);

        // Comentarios SQL
        $r6 = $service->validarSql('SELECT * FROM vista_reporte_citas -- comentario malicioso');
        $this->assertFalse($r6['es_valido']);

        // Introspección del motor
        $r7 = $service->validarSql('SELECT * FROM information_schema.tables');
        $this->assertFalse($r7['es_valido']);
    }

    public function test_sql_ast_validator_protects_confidential_clinical_tables(): void
    {
        $service = app(AiReporteService::class);

        // Historia clínica
        $r1 = $service->validarSql('SELECT * FROM historias_clinicas');
        $this->assertFalse($r1['es_valido']);
        $this->assertStringContainsString('confidencialidad clínica', (string) $r1['motivo']);

        // Atenciones médicas
        $r2 = $service->validarSql('SELECT * FROM atenciones_medicas');
        $this->assertFalse($r2['es_valido']);

        // Resultados discrete de laboratorio
        $r3 = $service->validarSql('SELECT * FROM detalles_resultado');
        $this->assertFalse($r3['es_valido']);

        // Usuarios y contraseñas
        $r4 = $service->validarSql('SELECT * FROM users');
        $this->assertFalse($r4['es_valido']);
    }

    public function test_ai_query_execution_creates_audit_trail_in_reportes_ia(): void
    {
        $admin = $this->crearUsuario('admin');
        $medico = Medico::factory()->create();
        $paciente = Paciente::factory()->create();

        Cita::factory()->create([
            'medico_id' => $medico->id,
            'paciente_id' => $paciente->id,
            'estado' => 'cancelada',
            'motivo_cancelacion' => 'Imprevisto laboral',
        ]);

        $service = app(AiReporteService::class);

        // Consulta válida
        $resultado = $service->ejecutarConsulta(
            pregunta: '¿Cuáles citas fueron canceladas?',
            userId: $admin->id
        );

        $this->assertTrue($resultado['ok']);
        $this->assertGreaterThanOrEqual(1, $resultado['total']);

        $auditoriaValida = ReporteIa::where('user_id', $admin->id)->latest()->first();
        $this->assertNotNull($auditoriaValida);
        $this->assertTrue($auditoriaValida->es_valido);
        $this->assertNull($auditoriaValida->motivo_rechazo);
        $this->assertEquals($resultado['total'], $auditoriaValida->filas_retornadas);

        // Consulta maliciosa rechazada
        $resultadoRechazado = $service->ejecutarConsulta(
            pregunta: 'Eliminar todas las citas',
            userId: $admin->id,
            sqlManual: 'DELETE FROM citas'
        );

        $this->assertFalse($resultadoRechazado['ok']);

        $auditoriaRechazada = ReporteIa::where('user_id', $admin->id)->latest()->first();
        $this->assertFalse($auditoriaRechazada->es_valido);
        $this->assertNotNull($auditoriaRechazada->motivo_rechazo);
    }

    public function test_ai_consultar_ajax_endpoint_returns_json(): void
    {
        $admin = $this->crearUsuario('admin');

        $response = $this->actingAs($admin)->postJson(route('reportes.ia.consultar'), [
            'pregunta' => '¿Cuántas citas canceladas hay?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'ok',
            'mensaje',
            'sql',
            'columnas',
            'filas',
            'total',
            'tiempo_ms',
        ]);
    }
}
