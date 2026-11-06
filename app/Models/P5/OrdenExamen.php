<?php

namespace App\Models\P5;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenExamen extends Model
{
    use HasFactory;

    protected $table = 'ordenes_examenes';

    protected $guarded = ['id'];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenLaboratorio::class, 'orden_laboratorio_id');
    }

    public function examen(): BelongsTo
    {
        return $this->belongsTo(ExamenLaboratorio::class, 'examen_laboratorio_id');
    }
}
