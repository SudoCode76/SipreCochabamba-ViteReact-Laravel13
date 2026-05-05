<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $table = 'item';

    protected $primaryKey = 'id_item';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'item',
        'id_unidad',
        'precio',
        'estado',
        'id_usuario',
        'cod',
        'grupo',
        'subgrupo',
        'especificacion',
        'ficha',
        'fecha_item',
    ];

    protected function casts(): array
    {
        return [
            'id_unidad' => 'integer',
            'precio' => 'float',
            'id_usuario' => 'integer',
            'grupo' => 'integer',
            'subgrupo' => 'integer',
            'fecha_item' => 'date',
        ];
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitMeasure::class, 'id_unidad', 'id_unidad_medida');
    }

    public function groupCatalog(): BelongsTo
    {
        return $this->belongsTo(GroupCatalog::class, 'grupo', 'id_grupo');
    }

    public function subgroupCatalog(): BelongsTo
    {
        return $this->belongsTo(SubgroupCatalog::class, 'subgrupo', 'id_subgrupo');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function itemInputs(): HasMany
    {
        return $this->hasMany(ItemInput::class, 'id_item', 'id_item');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
