<?php

namespace App\Models\P4;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialCita extends Model
{
    use HasFactory;

    protected $table = 'historial_citas';

    protected $guarded = ['id'];

    protected $casts = ['fecha_anterior' => 'datetime', 'fecha_nueva' => 'datetime', 'aceptacion_paciente' => 'boolean'];

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
