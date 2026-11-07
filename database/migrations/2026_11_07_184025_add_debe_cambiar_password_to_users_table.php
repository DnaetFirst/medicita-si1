<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'debe_cambiar_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('debe_cambiar_password')->default(false)->after('password');
            });
        }

        // Reparación garantizada: Todo paciente en la base de datos debe tener un usuario vinculado
        $rolPacienteId = DB::table('roles')->where('slug', 'paciente')->value('id');

        $pacientesHuerfanos = DB::table('pacientes')->whereNull('user_id')->get();
        foreach ($pacientesHuerfanos as $p) {
            $userExistente = DB::table('users')->where('ci', $p->ci)->first();
            if ($userExistente) {
                DB::table('pacientes')->where('id', $p->id)->update(['user_id' => $userExistente->id]);
            } else {
                $email = ! empty($p->email) ? $p->email : 'pac.'.$p->ci.'@medicita.local';
                if (DB::table('users')->where('email', $email)->exists()) {
                    $email = 'pac.'.$p->ci.'.'.$p->id.'@medicita.local';
                }

                $userId = DB::table('users')->insertGetId([
                    'name' => trim($p->nombres.' '.$p->apellidos),
                    'ci' => $p->ci,
                    'email' => $email,
                    'password' => Hash::make($p->ci),
                    'rol_id' => $rolPacienteId,
                    'telefono' => $p->telefono,
                    'estado' => 'activo',
                    'debe_cambiar_password' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('pacientes')->where('id', $p->id)->update(['user_id' => $userId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'debe_cambiar_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('debe_cambiar_password');
            });
        }
    }
};
