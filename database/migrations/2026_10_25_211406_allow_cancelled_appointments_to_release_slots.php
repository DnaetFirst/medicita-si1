<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table): void {
            $table->dropUnique(['slot_id']);
        });
        DB::statement("CREATE UNIQUE INDEX citas_active_slot_unique ON citas (slot_id) WHERE estado <> 'cancelada'");
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table): void {
            $table->unique('slot_id');
        });
        DB::statement('DROP INDEX citas_active_slot_unique');
    }
};
