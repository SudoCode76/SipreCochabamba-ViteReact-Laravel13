<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalculationPercentageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_porcentaje' => $this->id_porcentaje,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'porcentaje' => $this->porcentaje,
            'observacion' => $this->observacion,
            'estado' => $this->estado,
            'status_label' => match (strtoupper((string) $this->estado)) {
                'AC' => 'ACTIVO',
                'DC' => 'INACTIVO',
                default => strtoupper((string) $this->estado),
            },
            'available_actions' => [
                'edit' => true,
            ],
        ];
    }
}
