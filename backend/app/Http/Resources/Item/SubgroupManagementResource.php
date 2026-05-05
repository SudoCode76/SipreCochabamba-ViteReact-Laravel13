<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubgroupManagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_subgrupo' => $this->id_subgrupo,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'id_grupo' => $this->id_grupo,
            'nombre_grupo' => $this->nombre_grupo ?? $this->group?->nombre_grupo,
            'estado' => $this->estado,
            'status_label' => match (strtoupper((string) $this->estado)) {
                'AC' => 'ACTIVO',
                'DC' => 'INACTIVO',
                default => strtoupper((string) $this->estado),
            },
            'available_actions' => [
                'edit' => true,
                'delete' => true,
            ],
        ];
    }
}
