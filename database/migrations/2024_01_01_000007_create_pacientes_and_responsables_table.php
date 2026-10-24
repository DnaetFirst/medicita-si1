<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ci', 20)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->date('fecha_nacimiento');
            $table->string('genero', 20)->nullable();
            $table->string('grupo_sanguineo', 10)->nullable();
            $table->string('telefono', 20);
            $table->string('email')->nullable();
            $table->text('direccion')->nullable();
            $table->string('contacto_emergencia_nombre', 100)->nullable();
            $table->string('contacto_emergencia_telefono', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('responsables_autorizados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->string('ci', 20);
            $table->string('nombres', 100);
            $table->string('parentesco', 50); // Padre, Madre, Cónyuge, Hijo/a, Tutor Legal
            $table->string('telefono', 20);
            $table->date('fecha_limite_vigencia'); // Vigencia obligatoria
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['paciente_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsables_autorizados');
        Schema::dropIfExists('pacientes');
    }
};
