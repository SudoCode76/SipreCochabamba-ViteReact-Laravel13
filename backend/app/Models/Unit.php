<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'unidad';

    protected $primaryKey = 'id_unidad';

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
