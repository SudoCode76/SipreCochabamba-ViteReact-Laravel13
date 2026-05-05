<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'rol';

    protected $primaryKey = 'id_rol';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre_rol',
        'estado',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'id_rol', 'id_rol');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }

    public function isActive(): bool
    {
        return strtoupper((string) $this->estado) === 'AC';
    }
}
