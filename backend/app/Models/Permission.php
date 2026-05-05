<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    protected $table = 'permiso';

    protected $primaryKey = 'id_permiso';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_rol',
        'nombre_rol',
        'id_funcion',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'id_rol' => 'integer',
            'id_funcion' => 'integer',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_rol', 'id_rol');
    }

    public function systemFunction(): BelongsTo
    {
        return $this->belongsTo(SystemFunction::class, 'id_funcion', 'id_funcion');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
