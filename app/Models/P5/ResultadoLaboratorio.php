<?php

namespace App\Models\P5;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultadoLaboratorio extends Model
{
    use HasFactory;

    protected $table = 'resultados_laboratorio';

    protected $guarded = ['id'];

    protected $casts = [
        'version' => 'integer',
        'fecha_validacion' => 'datetime',
        'fecha_publicacion' => 'datetime',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenLaboratorio::class, 'orden_laboratorio_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_user_id');
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por_user_id');
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(ResultadoLaboratorio::class, 'corrige_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleResultado::class, 'resultado_laboratorio_id');
    }

    public function isBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    public function isValidado(): bool
    {
        return $this->estado === 'validado';
    }

    public function isPublicado(): bool
    {
        return $this->estado === 'publicado';
    }
}
