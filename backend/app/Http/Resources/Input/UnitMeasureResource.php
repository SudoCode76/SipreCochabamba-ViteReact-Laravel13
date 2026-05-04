<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitMeasureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_unidad_medida' => $this->id_unidad_medida,
            'descripcion' => $this->descripcion,
            'abreviatura' => $this->abreviatura,
            'estado' => $this->estado,
            'status_label' => strtoupper((string) $this->estado) === 'AC' ? 'ACTIVO' : 'INACTIVO',
            'available_actions' => [
                'edit' => true,
                'delete' => true,
            ],
        ];
    }
}
