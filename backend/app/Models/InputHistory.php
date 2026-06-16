<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InputHistory extends Model
{
    protected $table = 'historial_insumo';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'id_insumo',
        'id_log_insumo',
        'precio',
        'tipo',
        'id_categoria',
        'unidad_medida',
        'accion',
        'usuario',
        'fecha',
        'estado',
        'ip',
        'nombre_usuario',
    ];

    protected function casts(): array
    {
        return [
            'id_insumo' => 'integer',
            'id_log_insumo' => 'integer',
            'precio' => 'decimal:2',
            'tipo' => 'integer',
            'id_categoria' => 'integer',
            'unidad_medida' => 'integer',
            'usuario' => 'integer',
            'fecha' => 'datetime',
        ];
    }

    public function input(): BelongsTo
    {
        return $this->belongsTo(Input::class, 'id_insumo', 'id_insumo');
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(InputLog::class, 'id_log_insumo', 'id_log');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(InputQuote::class, 'id_log_insumo', 'id_log_insumo');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario', 'id_usuario');
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
}
