<?php

namespace App\Models\P5;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamenLaboratorio extends Model
{
    use HasFactory;

    protected $table = 'examenes_laboratorio';

    protected $guarded = ['id'];

    protected $casts = [
        'tiempo_estimado_horas' => 'integer',
        'activo' => 'boolean',
    ];

    public function ordenesExamenes(): HasMany
    {
        return $this->hasMany(OrdenExamen::class, 'examen_laboratorio_id');
    }

    public function detallesResultado(): HasMany
    {
        return $this->hasMany(DetalleResultado::class, 'examen_laboratorio_id');
    }
}
