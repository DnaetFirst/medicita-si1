<?php

namespace Database\Seeders;

use App\Models\P2\Especialidad;
use Illuminate\Database\Seeder;

class EspecialidadSeeder extends Seeder
{
    public function run(): void
    {
        $especialidades = [
            [
                'nombre' => 'Medicina General',
                'codigo' => 'MED-GEN',
                'descripcion' => 'Atención primaria y prevención integral de salud',
                'color' => '#0D7C7C', // Medical Teal
                'icono' => 'stethoscope',
                'activo' => true,
            ],
            [
                'nombre' => 'Pediatría',
                'codigo' => 'PED-01',
                'descripcion' => 'Cuidado médico de bebés, niños y adolescentes',
                'color' => '#4DB8C4', // Light Cyan
                'icono' => 'baby',
                'activo' => true,
            ],
            [
                'nombre' => 'Odontología',
                'codigo' => 'ODO-01',
                'descripcion' => 'Salud bucal, tratamientos dentales y estética',
                'color' => '#1A2B4A', // Deep Navy
                'icono' => 'smile',
                'activo' => true,
            ],
            [
                'nombre' => 'Cardiología',
                'codigo' => 'CARD-01',
                'descripcion' => 'Diagnóstico y tratamiento de afecciones cardíacas',
                'color' => '#E53E3E', // Soft Red
                'icono' => 'heart-pulse',
                'activo' => true,
            ],
            [
                'nombre' => 'Ginecología',
                'codigo' => 'GIN-01',
                'descripcion' => 'Salud reproductiva femenina y obstetricia',
                'color' => '#805AD5', // Purple
                'icono' => 'female',
                'activo' => true,
            ],
            [
                'nombre' => 'Geriatría',
                'codigo' => 'GER-01',
                'descripcion' => 'Atención y bienestar del adulto mayor',
                'color' => '#DD6B20', // Orange Warm
                'icono' => 'user-check',
                'activo' => true,
            ],
        ];

        foreach ($especialidades as $esp) {
            Especialidad::firstOrCreate(['codigo' => $esp['codigo']], $esp);
        }
    }
}
