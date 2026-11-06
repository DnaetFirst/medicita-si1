<?php

namespace App\Models\P5;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleResultado extends Model
{
    use HasFactory;

    protected $table = 'detalles_resultado';

    protected $guarded = ['id'];

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(ResultadoLaboratorio::class, 'resultado_laboratorio_id');
    }

    public function examen(): BelongsTo
    {
        return $this->belongsTo(ExamenLaboratorio::class, 'examen_laboratorio_id');
    }
}
