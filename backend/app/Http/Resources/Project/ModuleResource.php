<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_modulo' => $this->id_modulo,
            'nombre_modulo' => $this->nombre_modulo,
            'estado' => $this->estado,
            'status_label' => strtoupper((string) $this->estado) === 'AC' ? 'ACTIVO' : 'INACTIVO',
            'available_actions' => [
                'edit' => true,
                'delete' => true,
            ],
        ];
    }
}
