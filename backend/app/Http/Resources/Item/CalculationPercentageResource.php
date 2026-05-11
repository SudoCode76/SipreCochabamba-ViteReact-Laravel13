<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalculationPercentageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = strtoupper((string) $this->estado);

        return [
            'id' => $this->id_porcentaje,
            'internal_id' => $this->id_porcentaje,
            'id_porcentaje' => $this->id_porcentaje,
            'display_id' => $this->codigo,
            'code' => $this->codigo,
            'codigo' => $this->codigo,
            'description' => $this->descripcion,
            'descripcion' => $this->descripcion,
            'percentage' => $this->porcentaje,
            'porcentaje' => $this->porcentaje,
            'observation' => $this->observacion,
            'observacion' => $this->observacion,
            'status' => $status,
            'estado' => $status,
            'status_label' => match ($status) {
                'AC' => 'ACTIVO',
                'DC' => 'INACTIVO',
                default => $status,
            },
            'available_actions' => [
                'edit' => true,
                'select' => true,
            ],
        ];
    }
}
