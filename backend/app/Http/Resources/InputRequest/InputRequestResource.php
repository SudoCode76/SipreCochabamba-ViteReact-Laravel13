<?php

namespace App\Http\Resources\InputRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approvalStatus = (string) $this->estado_aprobacion;
        $updatedAt = $this->ultima_modificacion ?? $this->fecha_modificacion;

        return [
            'id' => $this->id_solicitud,
            'id_solicitud' => $this->id_solicitud,
            'descripcion' => $this->descripcion,
            'precio' => $this->precio !== null ? (float) $this->precio : null,
            'unidad_medida_id' => $this->unidad_medida,
            'nombre_unidad_medida' => $this->unitMeasure?->descripcion,
            'abreviatura' => $this->unitMeasure?->abreviatura,
            'tipo_id' => $this->tipo,
            'nombre_tipo' => $this->type?->descripcion,
            'fecha' => $this->fecha?->toDateString(),
            'usuario_solicitante' => $this->usuario_solicitante,
            'nombre_completo' => $this->requester?->funcionario,
            'ubicacion' => $this->ubicacion,
            'justificacion' => $this->justificacion,
            'estado_aprobacion' => $approvalStatus,
            'approval_status' => $approvalStatus,
            'approval_status_label' => $this->approvalStatusLabel($approvalStatus),
            'notificacion' => $this->notificacion,
            'observacion' => $this->when(isset($this->observacion), $this->observacion),
            'usuario_aprobacion' => $this->when(isset($this->usuario_aprobacion), $this->usuario_aprobacion),
            'fecha_aprobacion' => $this->when(isset($this->fecha_aprobacion) && $this->fecha_aprobacion !== null, fn () => $this->fecha_aprobacion?->toDateString()),
            'archivo' => $this->archivo,
            'archivo1' => $this->archivo1,
            'archivo2' => $this->archivo2,
            'unidad_medida' => $this->unidad_medida,
            'tipo' => $this->tipo,
            'fecha_modificacion' => $updatedAt?->toIso8601String(),
            'available_actions' => [
                'edit' => strtoupper(trim($approvalStatus)) === 'PD',
                'gestionar' => strtoupper(trim($approvalStatus)) === 'PD',
                'revertir' => in_array(strtoupper(trim($approvalStatus)), ['AP', 'RC'], true),
                'view_quotes' => true,
            ],
            'action_names' => match (strtoupper(trim($approvalStatus))) {
                'PD' => ['gestionar'],
                'AP', 'RC' => ['revertir'],
                default => [],
            },
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
