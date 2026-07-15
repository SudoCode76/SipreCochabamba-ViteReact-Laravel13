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
        'id_proyecto_raiz',
        'id_version_origen',
        'numero_version',
        'es_version_actual',
        'fecha_version',
        'fecha_finalizacion',
        'signature_access_mode',
        'signature_signers_configured_at',
        'signature_signers_locked_at',
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
            'id_proyecto_raiz' => 'integer',
            'id_version_origen' => 'integer',
            'numero_version' => 'integer',
            'es_version_actual' => 'boolean',
            'fecha_version' => 'datetime',
            'fecha_finalizacion' => 'datetime',
            'signature_access_mode' => 'string',
            'signature_signers_configured_at' => 'datetime',
            'signature_signers_locked_at' => 'datetime',
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

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'id_proyecto_raiz', 'id_proyecto_raiz');
    }

    public function percentageSnapshots(): HasMany
    {
        return $this->hasMany(ProjectPercentageSnapshot::class, 'id_proyecto', 'id_proyecto');
    }

    public function isFrozen(): bool
    {
        return strtoupper((string) $this->aprobado) === 'RV';
    }

    public function isCurrentVersion(): bool
    {
        return (bool) $this->es_version_actual;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
