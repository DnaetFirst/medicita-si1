<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes_ia', fn (Blueprint $table) => $table->longText('resultado_snapshot')->nullable());
    }

    public function down(): void
    {
        if (DB::table('reportes_ia')->whereNotNull('resultado_snapshot')->exists()) {
            throw new RuntimeException('Respalda los reportes conservados antes de revertir el esquema.');
        }
        Schema::table('reportes_ia', fn (Blueprint $table) => $table->dropColumn('resultado_snapshot'));
    }
};
