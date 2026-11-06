<?php

namespace Database\Factories\P4;

use App\Models\P2\Especialidad;
use App\Models\P3\Paciente;
use App\Models\P4\ListaEspera;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ListaEsperaFactory extends Factory
{
    protected $model = ListaEspera::class;

    public function definition(): array
    {
        return [
            'paciente_id' => Paciente::factory(),
            'especialidad_id' => Especialidad::factory(),
            'fecha_desde' => today(),
            'fecha_hasta' => today()->addDays(7),
            'franja_horaria' => 'cualquiera',
            'anticipacion_minima_horas' => 2,
            'estado' => 'activa',
            'fecha_inscripcion' => today(),
            'operacion_id' => (string) Str::uuid(),
        ];
    }
}
