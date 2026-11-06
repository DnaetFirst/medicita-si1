<?php

namespace Database\Factories\P6;

use App\Models\P6\ReporteIa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReporteIa>
 */
class ReporteIaFactory extends Factory
{
    protected $model = ReporteIa::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pregunta_usuario' => '¿Cuántas citas se cancelaron este mes?',
            'sql_generado' => 'SELECT * FROM vista_reporte_citas WHERE estado = \'cancelada\' LIMIT 10',
            'es_valido' => true,
            'motivo_rechazo' => null,
            'filas_retornadas' => 5,
            'tiempo_ms' => 12,
        ];
    }

    public function rechazado(string $motivo = 'Comando no permitido'): static
    {
        return $this->state(fn () => [
            'es_valido' => false,
            'motivo_rechazo' => $motivo,
            'filas_retornadas' => 0,
        ]);
    }
}
