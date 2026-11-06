<?php

namespace App\Models\P5;

use App\Models\P2\Medico;
use App\Models\P3\AtencionMedica;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenLaboratorio extends Model
{
    use HasFactory;

    protected $table = 'ordenes_laboratorio';

    protected $guarded = ['id'];

    protected $casts = [];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    public function atencionMedica(): BelongsTo
    {
        return $this->belongsTo(AtencionMedica::class, 'atencion_medica_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_user_id');
    }

    public function examenes(): BelongsToMany
    {
        return $this->belongsToMany(ExamenLaboratorio::class, 'ordenes_examenes', 'orden_laboratorio_id', 'examen_laboratorio_id')
            ->withPivot(['id', 'estado', 'observaciones'])
            ->withTimestamps();
    }

    public function ordenesExamenes(): HasMany
    {
        return $this->hasMany(OrdenExamen::class, 'orden_laboratorio_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoLaboratorio::class, 'orden_laboratorio_id');
    }

    public function resultadoPublicado(): ?ResultadoLaboratorio
    {
        return $this->resultados()->where('estado', 'publicado')->latest('version')->first();
    }

    public function isFinalizada(): bool
    {
        return $this->estado === 'finalizada';
    }

    public function isCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }
}
