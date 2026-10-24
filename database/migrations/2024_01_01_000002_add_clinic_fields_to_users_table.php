<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('rol_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
            $table->string('ci', 20)->nullable()->unique()->after('name');
            $table->string('telefono', 20)->nullable()->after('email');
            $table->string('estado', 20)->default('activo')->after('password'); // activo, inactivo, bloqueado
            $table->unsignedInteger('intentos_fallidos')->default(0)->after('estado');
            $table->timestamp('bloqueado_hasta')->nullable()->after('intentos_fallidos');
            $table->string('avatar')->nullable()->after('bloqueado_hasta');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['rol_id']);
            $table->dropColumn([
                'rol_id',
                'ci',
                'telefono',
                'estado',
                'intentos_fallidos',
                'bloqueado_hasta',
                'avatar'
            ]);
        });
    }
};
