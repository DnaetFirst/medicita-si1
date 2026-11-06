<?php

namespace App\Models\P6;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteIa extends Model
{
    use HasFactory;

    protected $table = 'reportes_ia';

    protected $fillable = [
        'user_id',
        'pregunta_usuario',
        'sql_generado',
        'es_valido',
        'motivo_rechazo',
        'filas_retornadas',
        'tiempo_ms',
        'resultado_snapshot',
    ];

    protected $casts = [
        'es_valido' => 'boolean',
        'filas_retornadas' => 'integer',
        'tiempo_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'resultado_snapshot' => 'encrypted:array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
