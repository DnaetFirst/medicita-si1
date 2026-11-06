<?php

namespace Database\Seeders;

use App\Models\P3\AtencionMedica;
use Illuminate\Database\Seeder;

class AtencionMedicaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $this->call(RolSeeder::class);
        AtencionMedica::factory()->create();
    }
}
