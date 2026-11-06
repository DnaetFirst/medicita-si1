<?php

namespace Database\Factories\P2;

use App\Models\P2\Medico;
use App\Models\P2\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Slot> */
class SlotFactory extends Factory
{
    protected $model = Slot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['medico_id' => Medico::factory(), 'fecha' => today()->addDay(), 'hora_inicio' => '09:00', 'hora_fin' => '09:15', 'estado' => 'disponible'];
    }
}
