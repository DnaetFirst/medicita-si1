<?php

namespace App\Models\P2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HorarioAtencion extends Model
{
    protected $table = 'horarios_atencion';

    protected $fillable = [
        'medico_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'duracion_slot_minutos',
        'descanso_inicio',
        'descanso_fin',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'duracion_slot_minutos' => 'integer',
        'dia_semana' => 'integer',
    ];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class, 'horario_id');
    }

    public function getNombreDiaAttribute(): string
    {
        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
        return $dias[$this->dia_semana] ?? 'Desconocido';
    }
}
