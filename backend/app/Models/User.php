<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'funcionario',
        'ci',
        'username',
        'clave',
        'estado',
        'id_unidad',
        'rol',
        'item',
        'fecha',
        'subalcaldia',
    ];

    protected $hidden = [
        'clave',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'id_unidad' => 'integer',
            'rol' => 'integer',
            'item' => 'integer',
            'subalcaldia' => 'integer',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->clave;
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol', 'id_rol');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'id_unidad', 'id_unidad');
    }

    public function activePermissions(): Builder
    {
        return Permission::query()
            ->with('systemFunction')
            ->where('id_rol', $this->rol)
            ->active()
            ->whereHas('systemFunction', fn (Builder $query): Builder => $query->active());
    }

    public function isActive(): bool
    {
        return strtoupper((string) $this->estado) === 'AC';
    }

    public function isAdministrator(): bool
    {
        $roleName = strtoupper(trim((string) $this->role?->nombre_rol));

        return $this->isActive()
            && $this->role?->isActive()
            && $roleName === 'ADMINISTRADOR';
    }
}
