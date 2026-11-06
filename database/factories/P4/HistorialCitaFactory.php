<?php

namespace Database\Factories\P4;

use App\Models\P4\Cita;
use App\Models\P4\HistorialCita;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<HistorialCita> */
class HistorialCitaFactory extends Factory
{
    protected $model = HistorialCita::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['cita_id' => Cita::factory(), 'operacion' => 'RESERVAR', 'estado_nuevo' => 'programada', 'fecha_nueva' => fn (array $a) => Cita::findOrFail($a['cita_id'])->fecha->toDateString().' 09:00:00', 'slot_nuevo_id' => fn (array $a) => Cita::findOrFail($a['cita_id'])->slot_id, 'medico_nuevo_id' => fn (array $a) => Cita::findOrFail($a['cita_id'])->medico_id, 'operacion_id' => (string) Str::uuid()];
    }
}
