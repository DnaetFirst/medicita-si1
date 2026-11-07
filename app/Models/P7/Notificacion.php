<?php

namespace App\Models\P7;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'evento_id', 'cita_id', 'estado_envio', 'intentos', 'enviado_at',
        'user_id',
        'tipo',
        'titulo',
        'mensaje',
        'leido',
    ];

    protected $casts = [
        'leido' => 'boolean',
        'enviado_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
