<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemFunction extends Model
{
    protected $table = 'funcion';

    protected $primaryKey = 'id_funcion';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre_funcion',
        'descripcion',
        'clase',
        'estado',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'id_funcion', 'id_funcion');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
