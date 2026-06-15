<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InputLog extends Model
{
    protected $table = 'log_insumo';

    protected $primaryKey = 'id_log';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'id_insumo',
        'precio',
        'tipo',
        'id_categoria',
        'unidad_medida',
        'accion',
        'usuario',
        'fecha',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'id_insumo' => 'integer',
            'precio' => 'decimal:2',
            'tipo' => 'integer',
            'id_categoria' => 'integer',
            'unidad_medida' => 'integer',
            'usuario' => 'integer',
            'fecha' => 'date',
        ];
    }

    public function input(): BelongsTo
    {
        return $this->belongsTo(Input::class, 'id_insumo', 'id_insumo');
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
