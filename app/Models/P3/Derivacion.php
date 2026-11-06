<?php

namespace App\Models\P3;

use App\Models\P2\Especialidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Derivacion extends Model
{
    use HasFactory;

    protected $table = 'derivaciones';

    protected $guarded = ['id'];

    protected $casts = [];

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(AtencionMedica::class, 'atencion_medica_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }
}
