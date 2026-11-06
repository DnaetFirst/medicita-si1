<?php

namespace App\Models\P3;

use App\Models\P4\Cita;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtencionMedica extends Model
{
    use HasFactory;

    protected $table = 'atenciones_medicas';

    protected $guarded = ['id'];

    protected $casts = ['es_correccion' => 'boolean'];

    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_clinica_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(AtencionMedica::class, 'corrige_id');
    }

    public function derivaciones(): HasMany
    {
        return $this->hasMany(Derivacion::class, 'atencion_medica_id');
    }
}
