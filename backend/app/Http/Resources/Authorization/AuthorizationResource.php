<?php

namespace App\Http\Resources\Authorization;

use App\Models\Authorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Authorization $authorization */
        $authorization = $this->resource;
        $authorizationId = $authorization->getAttribute($authorization->getKeyName());
        $status = strtoupper((string) $authorization->estado);
        $module = $this->moduleName($authorization);
        $isPending = $status === 'PE';

        return [
            'id' => $authorizationId,
            'id_autorizacion' => $authorizationId,
            'element_id' => $authorization->id_elemento,
            'id_elemento' => $authorization->id_elemento,
            'element' => $authorization->elemento,
            'elemento' => $authorization->elemento,
            'element_type' => $authorization->tipo_elemento,
            'tipo_elemento' => $authorization->tipo_elemento,
            'table' => $authorization->tabla,
            'tabla' => $authorization->tabla,
            'module' => $module,
            'modulo' => $module,
            'requester' => $authorization->requester?->funcionario,
            'solicitante_nombre' => $authorization->requester?->funcionario,
            'requester_id' => $authorization->solicitante,
            'solicitante' => $authorization->solicitante,
            'authorization_number' => $authorization->nro_autorizacion,
            'nro_autorizacion' => $authorization->nro_autorizacion,
            'admin_user_id' => $authorization->usuario_adm,
            'usuario_adm' => $authorization->usuario_adm,
            'status' => $status,
            'estado' => $status,
            'status_label' => $this->statusLabel($status),
            'date' => $authorization->fecha?->format('Y-m-d H:i:s'),
            'fecha' => $authorization->fecha?->format('Y-m-d H:i:s'),
            'processed_at' => $authorization->fecha_aut?->format('Y-m-d H:i:s'),
            'fecha_aut' => $authorization->fecha_aut?->format('Y-m-d H:i:s'),
            'available_actions' => [
                'process' => $isPending,
                'approve' => $isPending,
                'reject' => $isPending,
                'view' => true,
            ],
        ];
    }

    private function moduleName(Authorization $authorization): string
    {
        $module = trim((string) ($authorization->tipo_elemento ?: $authorization->tabla));

        return $module !== '' ? strtolower($module) : 'general';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'AP' => 'AUTORIZADO',
            'NP' => 'NO PROCEDE',
            default => 'PENDIENTE',
        };
    }
}
