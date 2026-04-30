<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubgroupCatalog extends Model
{
    protected $table = 'sub_grupo';

    protected $primaryKey = 'id_subgrupo';

    public $timestamps = false;

    protected $fillable = [
        'id_grupo',
        'descripcion',
        'estado',
        'codigo',
    ];

    protected function casts(): array
    {
        return [
            'id_grupo' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GroupCatalog::class, 'id_grupo', 'id_grupo');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
