<?php

namespace Tests\Feature;

use App\Models\P3\AtencionMedica;
use App\Models\P4\Cita;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CicloDosMigracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_legacy_ids_links_and_local_appointment_times(): void
    {
        $this->travelTo('2026-11-06 08:00:00');
        $this->seed(RolSeeder::class);
        $original = Cita::factory()->create(['estado' => 'cancelada']);
        $reprogramada = Cita::factory()->create(['estado' => 'confirmada', 'reprogramada_de_id' => $original->id]);
        $migracion = require database_path('migrations/2026_11_07_030554_add_cycle_two_foundation.php');
        $migracion->down();
        DB::table('citas')->where('id', $reprogramada->id)->update(['hora_checkin' => '2026-11-07 13:00:00']);
        $fecha = DB::table('citas')->where('id', $reprogramada->id)->value('fecha');
        $codigo = $reprogramada->codigo_ficha;

        $migracion->up();

        $this->assertDatabaseHas('citas', ['id' => $original->id, 'estado' => 'cancelada']);
        $this->assertDatabaseHas('citas', ['id' => $reprogramada->id, 'reprogramada_de_id' => $original->id, 'codigo_ficha' => $codigo,
            'fecha' => $fecha, 'hora' => '09:00', 'estado' => 'en_espera', 'hora_llegada' => '2026-11-07 09:00:00', 'hora_confirmacion' => null]);
        $this->assertDatabaseCount('citas', 2);
        $this->assertDatabaseCount('historial_citas', 0);
    }

    public function test_rollback_refuses_to_delete_clinical_records(): void
    {
        $this->seed(RolSeeder::class);
        $nota = AtencionMedica::factory()->create();
        $migracion = require database_path('migrations/2026_11_07_030554_add_cycle_two_foundation.php');
        try {
            $migracion->down();
            $this->fail('El rollback debió proteger las notas existentes.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('contiene registros', $exception->getMessage());
        }
        $this->assertModelExists($nota);
    }
}
