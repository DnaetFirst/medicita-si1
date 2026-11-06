<?php

namespace Database\Factories\P5;

use App\Models\P5\ExamenLaboratorio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExamenLaboratorioFactory extends Factory
{
    protected $model = ExamenLaboratorio::class;

    public function definition(): array
    {
        return [
            'codigo' => 'EX-'.strtoupper(Str::random(6)),
            'nombre' => $this->faker->words(3, true),
            'categoria' => 'Bioquímica',
            'descripcion' => $this->faker->sentence(),
            'unidad_medida' => 'mg/dL',
            'valores_referencia' => '70 - 100 mg/dL',
            'tiempo_estimado_horas' => 24,
            'activo' => true,
        ];
    }
}
