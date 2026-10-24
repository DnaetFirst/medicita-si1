<?php

namespace App\Models\P3;

use App\Models\P4\Cita;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Paciente extends Model
{
    protected $table = 'pacientes';

    protected $fillable = [
        'user_id',
        'ci',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'genero',
        'grupo_sanguineo',
        'telefono',
        'email',
        'direccion',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responsables(): HasMany
    {
        return $this->hasMany(ResponsableAutorizado::class, 'paciente_id');
    }

    public function responsableActivo(): HasOne
    {
        return $this->hasOne(ResponsableAutorizado::class, 'paciente_id')
            ->where('activo', true)
            ->where('fecha_limite_vigencia', '>=', now()->toDateString());
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'paciente_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }
}
