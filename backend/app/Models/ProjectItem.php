<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectItem extends Model
{
    protected $table = 'proyecto_item';

    protected $primaryKey = 'id_proyecto_item';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'id_item',
        'id_modulo',
        'estado',
        'cantidad',
        'fecha',
        'precio',
        'id_usuario',
        'prioridad',
    ];

    protected function casts(): array
    {
        return [
            'id_proyecto' => 'integer',
            'id_item' => 'integer',
            'id_modulo' => 'integer',
            'cantidad' => 'float',
            'fecha' => 'date',
            'precio' => 'float',
            'id_usuario' => 'integer',
            'prioridad' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id_proyecto', 'id_proyecto');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'id_item', 'id_item');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleCatalog::class, 'id_modulo', 'id_modulo');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
