<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre')->nullable();
            $table->string('rol', 50)->nullable();
            $table->string('accion', 100); // LOGIN, LOGOUT, CREAR_USUARIO, RESERVAR_CITA, etc.
            $table->string('modulo', 50);  // P1_Seguridad, P2_Medicos, P3_Pacientes, P4_Citas, etc.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('detalles')->nullable(); // JSON o descripción
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'modulo']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacoras');
    }
};
