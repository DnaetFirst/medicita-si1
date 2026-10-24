<?php

namespace App\Models\P2;

use App\Models\P4\Cita;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medico extends Model
{
    protected $table = 'medicos';

    protected $fillable = [
        'user_id',
        'especialidad_id',
        'nro_registro_medico',
        'telefono_contacto',
        'biografia',
        'estado',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioAtencion::class, 'medico_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class, 'medico_id');
    }

    public function bloqueos(): HasMany
    {
        return $this->hasMany(BloqueoAgenda::class, 'medico_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'medico_id');
    }
}
