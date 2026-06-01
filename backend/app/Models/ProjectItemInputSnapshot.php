<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectItemInputSnapshot extends Model
{
    protected $table = 'proyecto_item_insumo_snapshot';

    protected $primaryKey = 'id_snapshot';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_proyecto_item',
        'id_insumo',
        'descripcion',
        'tipo',
        'unidad',
        'cantidad',
        'precio_unitario',
        'parcial',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'id_proyecto_item' => 'integer',
            'id_insumo' => 'integer',
            'tipo' => 'integer',
            'cantidad' => 'float',
            'precio_unitario' => 'float',
            'parcial' => 'float',
        ];
    }

    public function projectItem(): BelongsTo
    {
        return $this->belongsTo(ProjectItem::class, 'id_proyecto_item', 'id_proyecto_item');
    }

    public function input(): BelongsTo
    {
        return $this->belongsTo(Input::class, 'id_insumo', 'id_insumo');
    }
}
