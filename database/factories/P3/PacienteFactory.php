<?php

namespace Database\Factories\P3;

use App\Models\P3\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Paciente> */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['ci' => fake()->unique()->numerify('##########'), 'nombres' => fake()->firstName(), 'apellidos' => fake()->lastName(), 'fecha_nacimiento' => '1995-05-12', 'telefono' => '70000001', 'activo' => true];
    }
}
