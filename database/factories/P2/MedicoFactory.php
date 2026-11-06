<?php

namespace Database\Factories\P2;

use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Medico> */
class MedicoFactory extends Factory
{
    protected $model = Medico::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory()->state(['rol_id' => Rol::where('slug', 'medico')->firstOrFail()->id, 'estado' => 'activo']), 'especialidad_id' => fn () => Especialidad::create(['nombre' => fake()->unique()->word(), 'codigo' => fake()->unique()->numerify('E#####'), 'activo' => true])->id, 'nro_registro_medico' => fake()->unique()->numerify('MED#######'), 'estado' => 'activo'];
    }
}
