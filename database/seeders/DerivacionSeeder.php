<?php

namespace Database\Seeders;

use App\Models\P3\Derivacion;
use Illuminate\Database\Seeder;

class DerivacionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $this->call(RolSeeder::class);
        Derivacion::factory()->create();
    }
}
