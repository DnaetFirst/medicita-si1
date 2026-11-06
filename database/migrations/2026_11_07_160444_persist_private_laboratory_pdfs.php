<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resultados_laboratorio', fn (Blueprint $table) => $table->longText('archivo_pdf_contenido')->nullable());
        DB::table('resultados_laboratorio')->whereNotNull('archivo_pdf_path')->orderBy('id')->chunkById(25, function ($resultados): void {
            foreach ($resultados as $resultado) {
                if (Storage::disk('local')->exists($resultado->archivo_pdf_path)) {
                    DB::table('resultados_laboratorio')->where('id', $resultado->id)->update(['archivo_pdf_contenido' => Crypt::encryptString(base64_encode(Storage::disk('local')->get($resultado->archivo_pdf_path)))]);
                }
            }
        });
    }

    public function down(): void
    {
        if (DB::table('resultados_laboratorio')->whereNotNull('archivo_pdf_contenido')->exists()) {
            throw new RuntimeException('Exporta y respalda los PDFs privados antes de revertir su almacenamiento.');
        }
        Schema::table('resultados_laboratorio', fn (Blueprint $table) => $table->dropColumn('archivo_pdf_contenido'));
    }
};
