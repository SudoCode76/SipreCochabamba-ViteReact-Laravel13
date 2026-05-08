<?php

namespace App\Http\Resources\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = strtoupper((string) $this->estado);
        $isActive = $status === 'AC';

        return [
            'id' => $this->id_rol,
            'name' => $this->nombre_rol,
            'nombre_rol' => $this->nombre_rol,
            'status' => $status,
            'estado' => $status,
            'status_label' => $isActive ? 'ACTIVO' : 'INACTIVO',
            'available_actions' => [
                'edit' => true,
                'assign_functions' => true,
                'update_status' => true,
                'activate' => ! $isActive,
                'deactivate' => $isActive,
                'select' => true,
            ],
        ];
    }
}
