<?php

namespace Database\Factories\P4;

use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cita> */
class CitaFactory extends Factory
{
    protected $model = Cita::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['codigo_ficha' => 'FCH-'.fake()->unique()->numerify('############'), 'paciente_id' => Paciente::factory(), 'slot_id' => Slot::factory()->state(['estado' => 'reservado']), 'medico_id' => fn (array $a) => Slot::findOrFail($a['slot_id'])->medico_id, 'fecha' => fn (array $a) => Slot::findOrFail($a['slot_id'])->fecha, 'hora' => fn (array $a) => Slot::findOrFail($a['slot_id'])->hora_inicio, 'estado' => 'programada', 'version' => 1];
    }
}
