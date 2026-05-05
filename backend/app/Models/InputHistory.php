<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'precio',
        'tipo',
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
            'precio' => 'decimal:2',
            'tipo' => 'integer',
            'unidad_medida' => 'integer',
            'usuario' => 'integer',
            'fecha' => 'datetime',
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

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitMeasure::class, 'unidad_medida', 'id_unidad_medida');
    }
}
