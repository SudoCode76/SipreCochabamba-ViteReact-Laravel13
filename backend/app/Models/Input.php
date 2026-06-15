<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Input extends Model
{
    protected $table = 'insumo';

    protected $primaryKey = 'id_insumo';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'unidad_medida',
        'precio',
        'tipo',
        'id_categoria',
        'estado',
        'usuario',
        'fecha',
        'solicitud',
        'cod',
        'fecha_cotiz',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'unidad_medida' => 'integer',
            'precio' => 'decimal:2',
            'tipo' => 'integer',
            'id_categoria' => 'integer',
            'usuario' => 'integer',
            'fecha' => 'date',
            'solicitud' => 'integer',
            'fecha_cotiz' => 'date',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(InputType::class, 'tipo', 'id_tipo');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InputCategory::class, 'id_categoria', 'id_categoria');
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitMeasure::class, 'unidad_medida', 'id_unidad_medida');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario', 'id_usuario');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(InputHistory::class, 'id_insumo', 'id_insumo');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InputLog::class, 'id_insumo', 'id_insumo');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(InputQuote::class, 'id_insumo', 'id_insumo');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('estado', 'AC');
    }
}
