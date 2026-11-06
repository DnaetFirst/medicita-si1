<?php

namespace App\Models\P3;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoriaClinica extends Model
{
    use HasFactory;

    protected $table = 'historias_clinicas';

    protected $guarded = ['id'];

    protected $casts = ['fecha_apertura' => 'date', 'version' => 'integer'];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function atenciones(): HasMany
    {
        return $this->hasMany(AtencionMedica::class, 'historia_clinica_id');
    }
}
