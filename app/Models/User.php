<?php

namespace App\Models;

use App\Models\P1\Bitacora;
use App\Models\P1\Rol;
use App\Models\P2\Medico;
use App\Models\P3\Paciente;
use App\Models\P7\Notificacion;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'rol_id',
        'ci',
        'email',
        'password',
        'telefono',
        'estado',
        'intentos_fallidos',
        'bloqueado_hasta',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'bloqueado_hasta' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function medico(): HasOne
    {
        return $this->hasOne(Medico::class, 'user_id');
    }

    public function paciente(): HasOne
    {
        return $this->hasOne(Paciente::class, 'user_id');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'user_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'user_id');
    }

    // RBAC Helpers
    public function isAdmin(): bool
    {
        return $this->rol && $this->rol->slug === 'admin';
    }

    public function isRecepcionista(): bool
    {
        return $this->rol && $this->rol->slug === 'recepcionista';
    }

    public function isMedico(): bool
    {
        return $this->rol && $this->rol->slug === 'medico';
    }

    public function isPaciente(): bool
    {
        return $this->rol && $this->rol->slug === 'paciente';
    }
}
