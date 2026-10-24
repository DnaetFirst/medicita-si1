<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ficha', 30)->unique(); // Ej: FCH-20261024-0001
            $table->foreignId('paciente_id')->constrained('pacientes');
            $table->foreignId('medico_id')->constrained('medicos');
            $table->foreignId('slot_id')->unique()->constrained('slots');
            $table->date('fecha');
            $table->time('hora');
            $table->string('estado', 20)->default('programada'); // programada, confirmada, en_curso, atendida, cancelada
            $table->text('motivo_consulta')->nullable();
            
            // Cancelación y reprogramación (CU9, CU10)
            $table->text('motivo_cancelacion')->nullable();
            $table->foreignId('cancelado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reprogramada_de_id')->nullable()->constrained('citas')->nullOnDelete();
            
            // Asistencia y consulta médica (CU11)
            $table->timestamp('hora_checkin')->nullable();
            $table->text('observaciones_atencion')->nullable();
            
            $table->timestamps();

            // Índices para consultas de agenda y reportes
            $table->index(['fecha', 'medico_id']);
            $table->index(['paciente_id', 'fecha']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
