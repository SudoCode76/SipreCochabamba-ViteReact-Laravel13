<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_categoria' => $this->id_categoria,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'status_label' => strtoupper((string) $this->estado) === 'AC' ? 'ACTIVO' : 'INACTIVO',
            'available_actions' => [
                'edit' => true,
            ],
        ];
    }
}
