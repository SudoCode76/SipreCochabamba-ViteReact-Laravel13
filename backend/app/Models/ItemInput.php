<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemInput extends Model
{
    protected $table = 'item_insumo';

    protected $primaryKey = 'id_item_insumo';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_insumo',
        'id_item',
        'estado',
        'id_usuario',
        'cantidad',
        'fecha',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            'id_insumo' => 'integer',
            'id_item' => 'integer',
            'id_usuario' => 'integer',
            'cantidad' => 'float',
            'fecha' => 'date',
            'tipo' => 'integer',
        ];
    }

    public function input(): BelongsTo
    {
        return $this->belongsTo(Input::class, 'id_insumo', 'id_insumo');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'id_item', 'id_item');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
