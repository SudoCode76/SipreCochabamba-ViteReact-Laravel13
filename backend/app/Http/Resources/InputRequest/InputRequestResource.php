<?php

namespace App\Http\Resources\InputRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approvalStatus = (string) $this->estado_aprobacion;

        return [
            'id_solicitud' => $this->id_solicitud,
            'descripcion' => $this->descripcion,
            'precio' => $this->precio !== null ? (float) $this->precio : null,
            'nombre_unidad_medida' => $this->unitMeasure?->descripcion,
            'abreviatura' => $this->unitMeasure?->abreviatura,
            'nombre_tipo' => $this->type?->descripcion,
            'fecha' => $this->fecha?->toDateString(),
            'nombre_completo' => $this->requester?->funcionario,
            'ubicacion' => $this->ubicacion,
            'justificacion' => $this->justificacion,
            'estado_aprobacion' => $approvalStatus,
            'approval_status' => $approvalStatus,
            'approval_status_label' => $this->approvalStatusLabel($approvalStatus),
            'notificacion' => $this->notificacion,
            'archivo' => $this->archivo,
            'archivo1' => $this->archivo1,
            'archivo2' => $this->archivo2,
            'usuario_solicitante' => $this->usuario_solicitante,
            'unidad_medida' => $this->unidad_medida,
            'tipo' => $this->tipo,
            'fecha_modificacion' => $this->fecha_modificacion?->toIso8601String(),
            'available_actions' => [
                'edit' => strtoupper(trim($approvalStatus)) === 'PD',
                'view_quotes' => true,
            ],
        ];
    }

    private function approvalStatusLabel(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'AP' => 'APROBADO',
            'RC' => 'RECHAZADO',
            default => 'PENDIENTE',
        };
    }
}
