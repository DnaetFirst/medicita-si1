<?php

namespace App\Models\P4;

use App\Models\P2\Slot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OfertaCupo extends Model
{
    use HasFactory;

    protected $table = 'ofertas_cupo';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_oferta' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'respondida_at' => 'datetime',
    ];

    public function listaEspera(): BelongsTo
    {
        return $this->belongsTo(ListaEspera::class, 'lista_espera_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'slot_id');
    }

    public function cita(): HasOne
    {
        return $this->hasOne(Cita::class, 'oferta_cupo_id');
    }

    public function isPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function isVencida(): bool
    {
        return $this->estado === 'vencida' || ($this->isPendiente() && $this->fecha_vencimiento->isPast());
    }
}
