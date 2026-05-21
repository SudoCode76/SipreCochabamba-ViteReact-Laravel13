<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $table = 'proyecto';

    protected $primaryKey = 'id_proyecto';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre_proyecto',
        'fecha',
        'ubicacion',
        'responsable',
        'solicitante',
        'observaciones',
        'aprobado',
        'fecha_aprob',
        'id_usuario',
        'estado',
        'es_plantilla',
        'nombre_responsable',
        'latitud',
        'longitud',
        'precio',
        'distrito',
        'zona',
        'otb',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_aprob' => 'date',
            'solicitante' => 'integer',
            'id_usuario' => 'integer',
            'precio' => 'float',
            'es_plantilla' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante', 'id_usuario');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class, 'id_proyecto', 'id_proyecto');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProjectHistory::class, 'id_proyecto', 'id_proyecto');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
