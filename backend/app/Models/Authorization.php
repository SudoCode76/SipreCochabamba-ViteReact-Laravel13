<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Authorization extends Model
{
    protected $table = 'autorizaciones';

    protected $primaryKey = 'id_autorizacion';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'num_sec',
        'id_elemento',
        'elemento',
        'tipo_elemento',
        'tabla',
        'solicitante',
        'estado',
        'nro_autorizacion',
        'usuario_adm',
        'fecha',
        'fecha_aut',
    ];

    protected function casts(): array
    {
        return [
            'num_sec' => 'integer',
            'id_elemento' => 'integer',
            'solicitante' => 'integer',
            'usuario_adm' => 'integer',
            'fecha' => 'datetime',
            'fecha_aut' => 'datetime',
        ];
    }

    public function getKeyName(): string
    {
        return Schema::hasColumn($this->getTable(), 'id_autorizacion')
            ? 'id_autorizacion'
            : 'num_sec';
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante', 'id_usuario');
    }
}
