<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Horarios base de atención semanal
        Schema::create('horarios_atencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 1 = Lunes, 2 = Martes, ..., 7 = Domingo
            $table->time('hora_inicio');               // ej. 08:00
            $table->time('hora_fin');                  // ej. 17:00
            $table->unsignedInteger('duracion_slot_minutos')->default(15);
            $table->time('descanso_inicio')->default('12:00'); // 1 hora fija de descanso
            $table->time('descanso_fin')->default('13:00');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['medico_id', 'dia_semana']);
        });

        // Slots o turnos generados para la agenda
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')->constrained('medicos')->cascadeOnDelete();
            $table->foreignId('horario_id')->nullable()->constrained('horarios_atencion')->nullOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('estado', 20)->default('disponible'); // disponible, reservado, bloqueado, descanso
            $table->timestamps();

            $table->unique(['medico_id', 'fecha', 'hora_inicio']);
            $table->index(['fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
        Schema::dropIfExists('horarios_atencion');
    }
};
