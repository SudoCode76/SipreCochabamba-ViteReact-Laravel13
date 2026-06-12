<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPercentageSnapshot extends Model
{
    protected $table = 'proyecto_porcentaje_snapshot';

    protected $primaryKey = 'id_snapshot';

    protected $fillable = [
        'id_proyecto',
        'formato',
        'codigo',
        'descripcion',
        'porcentaje',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'id_proyecto' => 'integer',
            'porcentaje' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id_proyecto', 'id_proyecto');
    }
}
