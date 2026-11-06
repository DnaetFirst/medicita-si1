<?php

namespace Database\Factories\P5;

use App\Models\P5\OrdenLaboratorio;
use App\Models\P5\ResultadoLaboratorio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ResultadoLaboratorioFactory extends Factory
{
    protected $model = ResultadoLaboratorio::class;

    public function definition(): array
    {
        return [
            'codigo_resultado' => 'RES-'.date('Ymd').'-'.strtoupper(Str::random(8)),
            'orden_laboratorio_id' => OrdenLaboratorio::factory(),
            'user_id' => User::factory(),
            'version' => 1,
            'estado' => 'borrador',
            'observaciones' => $this->faker->sentence(),
            'operacion_id' => (string) Str::uuid(),
        ];
    }
}
