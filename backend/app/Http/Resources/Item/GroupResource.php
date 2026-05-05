<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_grupo' => $this->id_grupo,
            'codigo_grupo' => $this->codigo_grupo,
            'nombre_grupo' => $this->nombre_grupo,
            'estado' => $this->estado,
            'status_label' => strtoupper((string) $this->estado) === 'AC' ? 'ACTIVO' : 'INACTIVO',
            'available_actions' => [
                'edit' => true,
                'delete' => true,
            ],
        ];
    }
}
