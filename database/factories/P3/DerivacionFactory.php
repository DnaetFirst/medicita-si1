<?php

namespace Database\Factories\P3;

use App\Models\P3\AtencionMedica;
use App\Models\P3\Derivacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Derivacion> */
class DerivacionFactory extends Factory
{
    protected $model = Derivacion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['atencion_medica_id' => AtencionMedica::factory(), 'especialidad_id' => fn (array $a) => AtencionMedica::findOrFail($a['atencion_medica_id'])->cita->medico->especialidad_id, 'user_id' => fn (array $a) => AtencionMedica::findOrFail($a['atencion_medica_id'])->user_id, 'motivo' => 'Seguimiento de prueba', 'operacion_id' => (string) Str::uuid(), 'estado' => 'emitida'];
    }
}
