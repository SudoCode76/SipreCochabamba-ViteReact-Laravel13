<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FndrCalculationPercentage extends Model
{
    protected $table = 'porcentaje_calculo_fndr';

    protected $primaryKey = 'id_porcentaje';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'porcentaje',
        'codigo',
        'observacion',
        'estado',
        'usuario',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'float',
            'usuario' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
