<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InputRequest extends Model
{
    protected $table = 'solicitud_insumo';

    protected $primaryKey = 'id_solicitud';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'precio',
        'unidad_medida',
        'tipo',
        'ubicacion',
        'justificacion',
        'usuario_solicitante',
        'estado_aprobacion',
        'notificacion',
        'archivo',
        'archivo1',
        'archivo2',
        'fecha',
        'fecha_modificacion',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'unidad_medida' => 'integer',
            'tipo' => 'integer',
            'usuario_solicitante' => 'integer',
            'fecha' => 'date',
            'fecha_modificacion' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(InputType::class, 'tipo', 'id_tipo');
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitMeasure::class, 'unidad_medida', 'id_unidad_medida');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_solicitante', 'id_usuario');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(InputQuote::class, 'id_solicitud', 'id_solicitud');
    }

    public function scopeApprovalStatus(Builder $query, string $status): Builder
    {
        return $query->where('estado_aprobacion', strtoupper($status));
    }
}
