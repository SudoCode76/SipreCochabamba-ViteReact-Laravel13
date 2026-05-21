<?php

namespace App\Http\Resources\Project;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_proyecto' => $this->id_proyecto,
            'nombre_proyecto' => $this->nombre_proyecto,
            'ubicacion' => $this->ubicacion,
            'fecha' => $this->fecha?->toDateString(),
            'responsable' => $this->responsable,
            'solicitante' => $this->solicitante,
            'solicitante_nombre' => $this->requester?->funcionario,
            'observaciones' => $this->observaciones,
            'aprobado' => $this->aprobado,
            'estado' => $this->estado,
            'es_plantilla' => (bool) $this->es_plantilla,
            'id_usuario' => $this->id_usuario,
            'fecha_aprob' => $this->fecha_aprob?->toDateString(),
            'nombre_responsable' => $this->nombre_responsable,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'precio' => $this->precio,
            'distrito' => $this->distrito,
            'zona' => $this->zona,
            'otb' => $this->otb,
        ];
    }
}
