<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InputQuote extends Model
{
    protected $table = 'cotizaciones';

    protected $primaryKey = 'id_cotizacion';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_insumo',
        'condicion',
        'estado',
        'id_log_insumo',
        'archivo',
        'fecha',
        'archivo1',
        'archivo2',
        'id_solicitud',
    ];

    protected function casts(): array
    {
        return [
            'id_insumo' => 'integer',
            'id_log_insumo' => 'integer',
            'fecha' => 'date',
            'id_solicitud' => 'integer',
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
}
