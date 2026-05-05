<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupCatalog extends Model
{
    protected $table = 'grupo';

    protected $primaryKey = 'id_grupo';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre_grupo',
        'estado',
        'codigo_grupo',
    ];

    public function subgroups(): HasMany
    {
        return $this->hasMany(SubgroupCatalog::class, 'id_grupo', 'id_grupo');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
