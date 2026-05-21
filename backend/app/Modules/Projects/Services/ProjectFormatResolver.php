<?php

namespace App\Modules\Projects\Services;

use InvalidArgumentException;

class ProjectFormatResolver
{
    public function toItemMode(?string $format): string
    {
        return match (strtoupper(trim((string) $format))) {
            'PCA', '', null => 'general',
            'PC_OBRAS' => 'obras',
            'PC_FPS' => 'fps',
            'PC_FNDR' => 'fndr',
            'PC_UPRE' => 'upre',
            'PC_PROMAN' => 'proman',
            default => throw new InvalidArgumentException('Formato de incidencia no soportado.'),
        };
    }
}
