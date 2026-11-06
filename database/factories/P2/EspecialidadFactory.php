<?php

namespace Database\Factories\P2;

use App\Models\P2\Especialidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Especialidad>
 */
class EspecialidadFactory extends Factory
{
    protected $model = Especialidad::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word().'logía',
            'codigo' => strtoupper(fake()->unique()->lexify('???')),
            'descripcion' => fake()->sentence(),
            'color' => '#0d7c7c',
            'icono' => 'heart',
            'activo' => true,
        ];
    }
}
