<?php

namespace App\Models\P4;

use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P3\Paciente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListaEspera extends Model
{
    use HasFactory;

    protected $table = 'listas_espera';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'fecha_inscripcion' => 'date',
        'anticipacion_minima_horas' => 'integer',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    public function citaPrevia(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function ofertas(): HasMany
    {
        return $this->hasMany(OfertaCupo::class, 'lista_espera_id');
    }

    public function isActiva(): bool
    {
        return $this->estado === 'activa';
    }
}
