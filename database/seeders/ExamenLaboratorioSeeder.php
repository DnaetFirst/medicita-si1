<?php

namespace Database\Seeders;

use App\Models\P5\ExamenLaboratorio;
use Illuminate\Database\Seeder;

class ExamenLaboratorioSeeder extends Seeder
{
    public function run(): void
    {
        $examenes = [
            [
                'codigo' => 'HEM-001',
                'nombre' => 'Hemograma Completo',
                'categoria' => 'Hematología',
                'descripcion' => 'Recuento de glóbulos rojos, blancos, hemoglobina, hematocrito y plaquetas.',
                'unidad_medida' => null,
                'valores_referencia' => 'Eritrocitos: 4.5-5.5 M/uL; Leucocitos: 4,500-11,000 /uL; Hemoglobina: 13.5-17.5 g/dL',
                'tiempo_estimado_horas' => 4,
                'activo' => true,
            ],
            [
                'codigo' => 'GLU-001',
                'nombre' => 'Glucosa Basal (En ayunas)',
                'categoria' => 'Bioquímica Clínica',
                'descripcion' => 'Medición de glucosa sérica en ayunas para detección y control de diabetes.',
                'unidad_medida' => 'mg/dL',
                'valores_referencia' => '70 - 100 mg/dL (Normal)',
                'tiempo_estimado_horas' => 2,
                'activo' => true,
            ],
            [
                'codigo' => 'CRE-001',
                'nombre' => 'Creatinina Sérica',
                'categoria' => 'Función Renal',
                'descripcion' => 'Evaluación de filtración glomerular y salud renal.',
                'unidad_medida' => 'mg/dL',
                'valores_referencia' => '0.7 - 1.3 mg/dL',
                'tiempo_estimado_horas' => 3,
                'activo' => true,
            ],
            [
                'codigo' => 'LIP-001',
                'nombre' => 'Perfil Lipídico Completo',
                'categoria' => 'Bioquímica Clínica',
                'descripcion' => 'Colesterol total, HDL, LDL y triglicéridos.',
                'unidad_medida' => 'mg/dL',
                'valores_referencia' => 'Colesterol < 200 mg/dL; Triglicéridos < 150 mg/dL',
                'tiempo_estimado_horas' => 6,
                'activo' => true,
            ],
            [
                'codigo' => 'URI-001',
                'nombre' => 'Examen General de Orina (EGO)',
                'categoria' => 'Uroanálisis',
                'descripcion' => 'Análisis físico, químico y microscópico de sedimento urinario.',
                'unidad_medida' => null,
                'valores_referencia' => 'Aspecto transparente, pH 5.0 - 7.0, Leucocitos 0-2/campo',
                'tiempo_estimado_horas' => 2,
                'activo' => true,
            ],
        ];

        foreach ($examenes as $examen) {
            ExamenLaboratorio::updateOrCreate(['codigo' => $examen['codigo']], $examen);
        }
    }
}
