<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FpsCalculationPercentage extends Model
{
    protected $table = 'porcentaje_calculo_fps';

    protected $primaryKey = 'id_porcentaje';

    public $incrementing = true;

    protected $keyType = 'int';

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
