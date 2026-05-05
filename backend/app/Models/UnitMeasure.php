<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UnitMeasure extends Model
{
    protected $table = 'unidad_medida';

    protected $primaryKey = 'id_unidad_medida';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'estado',
        'abreviatura',
        'usuario',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
