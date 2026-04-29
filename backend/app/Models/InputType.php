<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InputType extends Model
{
    protected $table = 'tipo_insumo';

    protected $primaryKey = 'id_tipo';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'estado',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
