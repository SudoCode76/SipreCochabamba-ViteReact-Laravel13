<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pendingDeleteAuthorizationId = $this->getAttribute('pending_delete_authorization_id');
        $hasPendingDeleteAuthorization = filled($pendingDeleteAuthorizationId);

        return [
            'id_insumo' => $this->id_insumo,
            'descripcion' => $this->descripcion,
            'precio' => $this->precio !== null ? (float) $this->precio : null,
            'id_tipo' => $this->tipo,
            'nombre_tipo' => $this->type?->descripcion,
            'id_categoria' => $this->id_categoria,
            'nombre_categoria' => $this->category?->descripcion,
            'id_unidad_medida' => $this->unidad_medida,
            'nombre_unidad_medida' => $this->unitMeasure?->descripcion,
            'abreviatura' => $this->unitMeasure?->abreviatura,
            'fecha_cotiz' => $this->fecha_cotiz?->toDateString(),
            'estado' => $this->estado,
            'is_duplicate' => (int) ($this->duplicate_count ?? 0) > 1,
            'duplicate_count' => (int) ($this->duplicate_count ?? 0),
            'duplicate_key' => $this->duplicate_key,
            'delete_authorization_status' => $hasPendingDeleteAuthorization ? 'pending' : 'none',
            'delete_authorization_label' => $hasPendingDeleteAuthorization ? 'ELIMINACIÓN EN PROCESO' : null,
            'delete_authorization_id' => $hasPendingDeleteAuthorization ? (int) $pendingDeleteAuthorizationId : null,
            'observacion' => $this->observacion,
            'tipo' => $this->tipo,
            'categoria' => $this->id_categoria,
            'unidad_medida' => $this->unidad_medida,
            'id' => $this->id_insumo,
            'description' => $this->descripcion,
            'price' => $this->precio !== null ? (float) $this->precio : null,
            'status' => $this->estado,
            'isDuplicate' => (int) ($this->duplicate_count ?? 0) > 1,
            'duplicateCount' => (int) ($this->duplicate_count ?? 0),
            'duplicateKey' => $this->duplicate_key,
            'deleteAuthorizationStatus' => $hasPendingDeleteAuthorization ? 'pending' : 'none',
            'deleteAuthorizationLabel' => $hasPendingDeleteAuthorization ? 'ELIMINACIÓN EN PROCESO' : null,
            'deleteAuthorizationId' => $hasPendingDeleteAuthorization ? (int) $pendingDeleteAuthorizationId : null,
            'date' => $this->fecha?->toDateString(),
            'request_id' => $this->solicitud,
            'code' => $this->cod,
            'quote_date' => $this->fecha_cotiz?->toDateString(),
            'observation' => $this->observacion,
            'type' => $this->whenLoaded('type', fn (): ?array => $this->type ? [
                'id' => $this->type->id_tipo,
                'description' => $this->type->descripcion,
                'status' => $this->type->estado,
            ] : null),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category ? [
                'id' => $this->category->id_categoria,
                'description' => $this->category->descripcion,
                'status' => $this->category->estado,
            ] : null),
            'unit_measure' => $this->whenLoaded('unitMeasure', fn (): ?array => $this->unitMeasure ? [
                'id' => $this->unitMeasure->id_unidad_medida,
                'description' => $this->unitMeasure->descripcion,
                'abbreviation' => $this->unitMeasure->abreviatura,
                'status' => $this->unitMeasure->estado,
            ] : null),
            'user' => $this->whenLoaded('creator', fn (): ?array => $this->creator ? [
                'id' => $this->creator->id_usuario,
                'full_name' => $this->creator->funcionario,
                'username' => $this->creator->username,
                'status' => $this->creator->estado,
            ] : null),
        ];
    }
}
