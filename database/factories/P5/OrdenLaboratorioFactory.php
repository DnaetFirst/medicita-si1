<?php

namespace Database\Factories\P5;

use App\Models\P2\Medico;
use App\Models\P3\Paciente;
use App\Models\P5\OrdenLaboratorio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrdenLaboratorioFactory extends Factory
{
    protected $model = OrdenLaboratorio::class;

    public function definition(): array
    {
        return [
            'codigo_orden' => 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(8)),
            'paciente_id' => Paciente::factory(),
            'medico_id' => Medico::factory(),
            'estado' => 'emitida',
            'observaciones' => $this->faker->sentence(),
            'operacion_id' => (string) Str::uuid(),
        ];
    }
}
