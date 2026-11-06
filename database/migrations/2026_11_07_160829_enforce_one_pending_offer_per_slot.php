<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ofertas_cupo')->where('estado', 'pendiente')->where('fecha_vencimiento', '<=', now())->update(['estado' => 'vencida', 'respondida_at' => now()]);
        if (DB::table('ofertas_cupo')->select('slot_id')->where('estado', 'pendiente')->groupBy('slot_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Existen ofertas pendientes duplicadas por cupo. Revísalas antes del despliegue; se preservaron los registros.');
        }
        DB::statement("CREATE UNIQUE INDEX oferta_pendiente_slot_unique ON ofertas_cupo (slot_id) WHERE estado = 'pendiente'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS oferta_pendiente_slot_unique');
    }
};
