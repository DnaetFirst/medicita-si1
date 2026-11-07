<?php

namespace App\Models\P3;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponsableAutorizado extends Model
{
    protected $table = 'responsables_autorizados';

    protected $fillable = [
        'user_id', 'vigencia_inicio', 'puede_gestionar_citas', 'puede_ver_resultados',
        'paciente_id',
        'ci',
        'nombres',
        'parentesco',
        'telefono',
        'fecha_limite_vigencia',
        'activo',
    ];

    protected $casts = [
        'fecha_limite_vigencia' => 'date',
        'activo' => 'boolean', 'vigencia_inicio' => 'date', 'puede_gestionar_citas' => 'boolean', 'puede_ver_resultados' => 'boolean',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isVigente(): bool
    {
        return $this->activo && $this->fecha_limite_vigencia->gte(today()) && (! $this->vigencia_inicio || $this->vigencia_inicio->lte(today()));
    }
}
