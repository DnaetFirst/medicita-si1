<?php

namespace App\Models\P1;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'bitacoras';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'usuario_nombre',
        'rol',
        'accion',
        'modulo',
        'ip_address',
        'user_agent',
        'detalles',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
