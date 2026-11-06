<?php

namespace Database\Seeders;

use App\Models\P3\HistoriaClinica;
use Illuminate\Database\Seeder;

class HistoriaClinicaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $this->call(RolSeeder::class);
        HistoriaClinica::factory()->create();
    }
}
