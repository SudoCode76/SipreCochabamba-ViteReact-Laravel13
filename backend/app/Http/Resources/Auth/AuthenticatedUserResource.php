<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_usuario,
            'full_name' => $this->funcionario,
            'ci' => $this->ci,
            'username' => $this->username,
            'status' => $this->estado,
            'signature_image_url' => $this->firma_imagen_path
                ? '/storage/'.ltrim((string) $this->firma_imagen_path, '/')
                : null,
            'is_admin' => $this->isAdministrator(),
            'role' => $this->whenLoaded('role', fn (): ?array => $this->role ? [
                'id' => $this->role->id_rol,
                'name' => $this->role->nombre_rol,
                'status' => $this->role->estado,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit ? [
                'id' => $this->unit->id_unidad,
                'description' => $this->unit->descripcion,
                'status' => $this->unit->estado,
            ] : null),
            'permissions' => $this->when(isset($this->permissions), function (): array {
                return collect($this->permissions)
                    ->map(fn ($permission): array => [
                        'id' => $permission->id_permiso,
                        'description' => $permission->descripcion,
                        'status' => $permission->estado,
                        'function' => $permission->systemFunction ? [
                            'id' => $permission->systemFunction->id_funcion,
                            'name' => $permission->systemFunction->nombre_funcion,
                            'description' => $permission->systemFunction->descripcion,
                            'class' => $permission->systemFunction->clase,
                            'status' => $permission->systemFunction->estado,
                        ] : null,
                    ])
                    ->values()
                    ->all();
            }, []),
        ];
    }
}
