<?php

namespace App\Models\P2;

use App\Models\P4\Cita;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Slot extends Model
{
    protected $table = 'slots';

    protected $fillable = [
        'medico_id',
        'horario_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(HorarioAtencion::class, 'horario_id');
    }

    public function cita(): HasOne
    {
        return $this->hasOne(Cita::class, 'slot_id');
    }

    public function isDisponible(): bool
    {
        return $this->estado === 'disponible';
    }
}
