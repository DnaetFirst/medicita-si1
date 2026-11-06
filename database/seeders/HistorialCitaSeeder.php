<?php

namespace Database\Seeders;

use App\Models\P4\HistorialCita;
use Illuminate\Database\Seeder;

class HistorialCitaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $this->call(RolSeeder::class);
        HistorialCita::factory()->create();
    }
}
