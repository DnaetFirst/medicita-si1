<?php

namespace Database\Factories\P3;

use App\Models\P3\AtencionMedica;
use App\Models\P3\HistoriaClinica;
use App\Models\P4\Cita;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AtencionMedica> */
class AtencionMedicaFactory extends Factory
{
    protected $model = AtencionMedica::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['cita_id' => Cita::factory()->state(['estado' => 'en_atencion']), 'historia_clinica_id' => fn (array $a) => HistoriaClinica::factory()->create(['paciente_id' => Cita::findOrFail($a['cita_id'])->paciente_id])->id, 'user_id' => fn (array $a) => Cita::findOrFail($a['cita_id'])->medico->user_id, 'motivo_consulta' => 'Control de prueba', 'diagnostico' => 'Registro sintético', 'operacion_id' => (string) Str::uuid(), 'es_correccion' => false];
    }
}
