<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSignatureAccessUpdate = $this->accion === 'signature_access_updated';

        return [
            'id' => $this->id_historial,
            'project_id' => $this->id_proyecto,
            'user_id' => $this->id_usuario,
            'user_name' => $this->usuario_nombre,
            'action' => $this->accion,
            'title' => $isSignatureAccessUpdate ? 'Se actualizó el acceso al proyecto' : $this->titulo,
            'detail' => $isSignatureAccessUpdate
                ? 'Se modificaron los usuarios autorizados para consultar y editar el proyecto, así como para firmar sus documentos.'
                : $this->detalle,
            'metadata' => $this->metadata ?? [],
            'ip' => $this->ip,
            'occurred_at' => $this->fecha_hora?->toIso8601String(),
        ];
    }
}
