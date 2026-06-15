<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InputCategory extends Model
{
    protected $table = 'categoria_insumo';

    protected $primaryKey = 'id_categoria';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'estado',
        'usuario',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'usuario' => 'integer',
            'fecha' => 'date',
        ];
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(Input::class, 'id_categoria', 'id_categoria');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
