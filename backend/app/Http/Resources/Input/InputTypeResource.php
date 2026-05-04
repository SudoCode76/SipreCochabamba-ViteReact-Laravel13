<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_tipo' => $this->id_tipo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'status_label' => strtoupper((string) $this->estado) === 'AC' ? 'ACTIVO' : 'INACTIVO',
            'available_actions' => [
                'edit' => true,
            ],
        ];
    }
}
