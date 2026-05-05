<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ObrasCalculationPercentage extends Model
{
    protected $table = 'porcentaje_calculo_obras';

    protected $primaryKey = 'id_porcentaje';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'porcentaje',
        'estado',
        'usuario',
        'codigo',
        'observacion',
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
