<?php

namespace App\Http\Resources\Function;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FunctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = strtoupper((string) $this->estado);
        $isActive = $status === 'AC';

        return [
            'id' => $this->id_funcion,
            'name' => $this->nombre_funcion,
            'nombre_funcion' => $this->nombre_funcion,
            'description' => $this->descripcion,
            'descripcion' => $this->descripcion,
            'class' => $this->clase,
            'controller' => $this->clase,
            'clase' => $this->clase,
            'status' => $status,
            'estado' => $status,
            'status_label' => $status === 'AC' ? 'ACTIVO' : 'INACTIVO',
            'available_actions' => [
                'edit' => true,
                'update_status' => true,
                'activate' => ! $isActive,
                'deactivate' => $isActive,
                'select' => true,
            ],
        ];
    }
}
