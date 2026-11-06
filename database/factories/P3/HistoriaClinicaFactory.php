<?php

namespace Database\Factories\P3;

use App\Models\P3\HistoriaClinica;
use App\Models\P3\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HistoriaClinica> */
class HistoriaClinicaFactory extends Factory
{
    protected $model = HistoriaClinica::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['paciente_id' => Paciente::factory(), 'nro_expediente' => 'HC-'.fake()->unique()->numerify('########'), 'fecha_apertura' => today(), 'version' => 1];
    }
}
