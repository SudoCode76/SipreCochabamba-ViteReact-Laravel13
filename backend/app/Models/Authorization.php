<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Authorization extends Model
{
    protected $table = 'autorizaciones';

    protected $primaryKey = 'id_autorizacion';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_elemento',
        'elemento',
        'tipo_elemento',
        'tabla',
        'solicitante',
        'estado',
        'nro_autorizacion',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'id_elemento' => 'integer',
            'solicitante' => 'integer',
            'fecha' => 'datetime',
        ];
    }
}
