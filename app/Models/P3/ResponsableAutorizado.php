<?php

namespace App\Models\P3;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponsableAutorizado extends Model
{
    protected $table = 'responsables_autorizados';

    protected $fillable = [
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
        'activo' => 'boolean',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function isVigente(): bool
    {
        return $this->activo && $this->fecha_limite_vigencia->gte(today());
    }
}
