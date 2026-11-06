<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $procedencias = DB::table('citas')->whereNotNull('reprogramada_de_id')->pluck('reprogramada_de_id', 'id');
        $this->convertirTimestamps(config('app.legacy_timestamp_timezone'), 'America/La_Paz');
        DB::table('roles')->insertOrIgnore([
            'slug' => 'lab', 'nombre' => 'Laboratorio', 'descripcion' => 'Órdenes y resultados autorizados',
            'permisos' => json_encode(['laboratorio.*']), 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::table('responsables_autorizados', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('vigencia_inicio')->nullable();
            $table->boolean('puede_gestionar_citas')->default(false);
            $table->boolean('puede_ver_resultados')->default(false);
        });
        Schema::table('bloqueos_agenda', function (Blueprint $table): void {
            $table->timestamp('levantado_at')->nullable();
        });
        Schema::table('citas', function (Blueprint $table): void {
            $table->timestamp('hora_confirmacion')->nullable();
            $table->timestamp('hora_llegada')->nullable();
            $table->timestamp('hora_inicio_atencion')->nullable();
            $table->timestamp('hora_fin_atencion')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('operacion_id')->nullable()->unique();
        });
        DB::table('citas')->where('estado', 'confirmada')->whereNotNull('hora_checkin')
            ->update(['estado' => 'en_espera', 'hora_llegada' => DB::raw('hora_checkin')]);
        DB::table('citas')->where('estado', 'en_curso')->update(['estado' => 'en_atencion']);
        DB::table('citas')->where('estado', 'no_asistio')->update(['estado' => 'inasistencia']);
        Schema::create('historial_citas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('operacion', 50);
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->dateTime('fecha_anterior')->nullable();
            $table->dateTime('fecha_nueva');
            $table->unsignedBigInteger('slot_anterior_id')->nullable();
            $table->unsignedBigInteger('slot_nuevo_id');
            $table->unsignedBigInteger('medico_anterior_id')->nullable();
            $table->unsignedBigInteger('medico_nuevo_id');
            $table->text('motivo')->nullable();
            $table->uuid('operacion_id')->unique();
            $table->boolean('aceptacion_paciente')->default(false);
            $table->timestamps();
            $table->index(['cita_id', 'id']);
        });
        Schema::create('historias_clinicas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paciente_id')->unique()->constrained('pacientes')->restrictOnDelete();
            $table->string('nro_expediente')->unique();
            $table->text('antecedentes')->nullable();
            $table->text('medicacion_habitual')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->date('fecha_apertura');
            $table->timestamps();
        });
        Schema::create('atenciones_medicas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('historia_clinica_id')->constrained('historias_clinicas')->restrictOnDelete();
            $table->foreignId('cita_id')->constrained('citas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('motivo_consulta');
            $table->text('hallazgos')->nullable();
            $table->text('diagnostico');
            $table->text('tratamiento')->nullable();
            $table->text('indicaciones')->nullable();
            $table->text('antecedentes')->nullable();
            $table->text('medicacion_habitual')->nullable();
            $table->boolean('es_correccion')->default(false);
            $table->foreignId('corrige_id')->nullable()->unique()->constrained('atenciones_medicas')->restrictOnDelete();
            $table->text('motivo_correccion')->nullable();
            $table->uuid('operacion_id')->unique();
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX atencion_original_cita_unique ON atenciones_medicas (cita_id) WHERE es_correccion = false');
        Schema::create('derivaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('atencion_medica_id')->constrained('atenciones_medicas')->restrictOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('motivo');
            $table->text('indicaciones')->nullable();
            $table->string('estado')->default('emitida');
            $table->uuid('operacion_id')->unique();
            $table->timestamps();
        });
        Schema::table('citas', function (Blueprint $table): void {
            $table->foreignId('derivacion_id')->nullable()->unique()->constrained('derivaciones')->restrictOnDelete();
        });
        Schema::table('notificaciones', function (Blueprint $table): void {
            $table->string('evento_id')->nullable()->unique();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->restrictOnDelete();
            $table->string('estado_envio')->default('pendiente');
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamp('enviado_at')->nullable();
        });
        DB::statement('DROP INDEX IF EXISTS citas_active_slot_unique');
        DB::statement("CREATE UNIQUE INDEX citas_active_slot_unique ON citas (slot_id) WHERE estado NOT IN ('cancelada', 'postergada')");
        foreach ($procedencias as $id => $anteriorId) {
            DB::table('citas')->where('id', $id)->update(['reprogramada_de_id' => $anteriorId]);
        }
    }

    public function down(): void
    {
        $procedencias = DB::table('citas')->whereNotNull('reprogramada_de_id')->pluck('reprogramada_de_id', 'id');
        if (DB::table('historial_citas')->exists() || DB::table('atenciones_medicas')->exists() || DB::table('historias_clinicas')->exists()) {
            throw new RuntimeException('La etapa 1 contiene registros. Restaura un respaldo verificado para revertir sin perderlos.');
        }
        DB::statement('DROP INDEX IF EXISTS citas_active_slot_unique');
        Schema::table('notificaciones', fn (Blueprint $table) => $table->dropUnique(['evento_id']));
        Schema::table('citas', function (Blueprint $table): void {
            $table->dropUnique(['derivacion_id']);
            $table->dropUnique(['operacion_id']);
        });
        Schema::table('notificaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cita_id');
            $table->dropColumn(['evento_id', 'estado_envio', 'intentos', 'enviado_at']);
        });
        Schema::table('citas', fn (Blueprint $table) => $table->dropConstrainedForeignId('derivacion_id'));
        Schema::dropIfExists('derivaciones');
        Schema::dropIfExists('atenciones_medicas');
        Schema::dropIfExists('historias_clinicas');
        Schema::dropIfExists('historial_citas');
        Schema::table('citas', fn (Blueprint $table) => $table->dropColumn(['hora_confirmacion', 'hora_llegada', 'hora_inicio_atencion', 'hora_fin_atencion', 'version', 'operacion_id']));
        Schema::table('responsables_autorizados', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['vigencia_inicio', 'puede_gestionar_citas', 'puede_ver_resultados']);
        });
        Schema::table('bloqueos_agenda', fn (Blueprint $table) => $table->dropColumn('levantado_at'));
        DB::table('citas')->where('estado', 'en_espera')->update(['estado' => 'confirmada']);
        DB::table('citas')->where('estado', 'en_atencion')->update(['estado' => 'en_curso']);
        DB::table('citas')->where('estado', 'inasistencia')->update(['estado' => 'no_asistio']);
        DB::statement("CREATE UNIQUE INDEX citas_active_slot_unique ON citas (slot_id) WHERE estado <> 'cancelada'");
        foreach ($procedencias as $id => $anteriorId) {
            DB::table('citas')->where('id', $id)->update(['reprogramada_de_id' => $anteriorId]);
        }
        $this->convertirTimestamps('America/La_Paz', config('app.legacy_timestamp_timezone'));
    }

    private function convertirTimestamps(string $origen, string $destino): void
    {
        new DateTimeZone($origen);
        if ($origen === $destino) {
            return;
        }

        $tablas = ['users', 'roles', 'especialidades', 'medicos', 'horarios_atencion', 'slots', 'bloqueos_agenda', 'pacientes', 'responsables_autorizados', 'citas', 'notificaciones', 'bitacoras'];
        foreach ($tablas as $tabla) {
            $columnas = array_values(array_filter(['created_at', 'updated_at', ...match ($tabla) {
                'users' => ['email_verified_at', 'bloqueado_hasta'],
                'citas' => ['hora_checkin'],
                default => [],
            }], fn (string $columna): bool => Schema::hasColumn($tabla, $columna)));
            DB::table($tabla)->select(['id', ...$columnas])->orderBy('id')->chunkById(200, function ($filas) use ($tabla, $columnas, $origen, $destino): void {
                foreach ($filas as $fila) {
                    $cambios = [];
                    foreach ($columnas as $columna) {
                        if ($fila->$columna !== null) {
                            $cambios[$columna] = CarbonImmutable::parse($fila->$columna, $origen)->setTimezone($destino)->format('Y-m-d H:i:s');
                        }
                    }
                    if ($cambios !== []) {
                        DB::table($tabla)->where('id', $fila->id)->update($cambios);
                    }
                }
            });
        }
    }
};
