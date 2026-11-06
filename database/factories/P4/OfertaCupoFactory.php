<?php

namespace Database\Factories\P4;

use App\Models\P2\Slot;
use App\Models\P4\ListaEspera;
use App\Models\P4\OfertaCupo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OfertaCupoFactory extends Factory
{
    protected $model = OfertaCupo::class;

    public function definition(): array
    {
        return [
            'lista_espera_id' => ListaEspera::factory(),
            'slot_id' => Slot::factory(),
            'estado' => 'pendiente',
            'fecha_oferta' => now(),
            'fecha_vencimiento' => now()->addMinutes(10),
            'operacion_id' => (string) Str::uuid(),
        ];
    }
}
