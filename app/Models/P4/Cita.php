<?php

namespace App\Models\P4;

use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cita extends Model
{
    protected $table = 'citas';

    protected $fillable = [
        'codigo_ficha',
        'paciente_id',
        'medico_id',
        'slot_id',
        'fecha',
        'hora',
        'estado',
        'motivo_consulta',
        'motivo_cancelacion',
        'cancelado_por_user_id',
        'reprogramada_de_id',
        'hora_checkin',
        'observaciones_atencion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_checkin' => 'datetime',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'slot_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_user_id');
    }

    public function citaPrevia(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'reprogramada_de_id');
    }

    public function citasReprogramadas(): HasMany
    {
        return $this->hasMany(Cita::class, 'reprogramada_de_id');
    }

    // State helpers
    public function isProgramada(): bool
    {
        return $this->estado === 'programada';
    }

    public function isConfirmada(): bool
    {
        return $this->estado === 'confirmada';
    }

    public function isCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    public function isAtendida(): bool
    {
        return $this->estado === 'atendida';
    }
}
