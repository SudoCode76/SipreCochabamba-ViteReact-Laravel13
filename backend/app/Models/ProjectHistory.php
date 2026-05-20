<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectHistory extends Model
{
    protected $table = 'proyecto_historial';

    protected $primaryKey = 'id_historial';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'id_usuario',
        'usuario_nombre',
        'accion',
        'titulo',
        'detalle',
        'metadata',
        'ip',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'id_proyecto' => 'integer',
            'id_usuario' => 'integer',
            'metadata' => 'array',
            'fecha_hora' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id_proyecto', 'id_proyecto');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
