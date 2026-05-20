<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleCatalog extends Model
{
    protected $table = 'modulo';

    protected $primaryKey = 'id_modulo';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre_modulo',
        'estado',
        'id_usuario',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'id_usuario' => 'integer',
            'fecha' => 'date',
        ];
    }

    public function projectItems(): HasMany
    {
        return $this->hasMany(ProjectItem::class, 'id_modulo', 'id_modulo');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
