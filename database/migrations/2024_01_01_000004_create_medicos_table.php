<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades');
            $table->string('nro_registro_medico', 50)->unique(); // Matrícula profesional
            $table->string('telefono_contacto', 20)->nullable();
            $table->text('biografia')->nullable();
            $table->string('estado', 20)->default('activo'); // activo, inactivo, de_baja
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicos');
    }
};
