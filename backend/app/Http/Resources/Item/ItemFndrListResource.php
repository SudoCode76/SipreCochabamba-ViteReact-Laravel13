<?php

namespace App\Http\Resources\Item;

use App\Services\Items\ItemActionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemFndrListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = (string) $this['status'];

        return [
            'id_item' => $this['id_item'],
            'name' => $this['name'],
            'calculated_price' => $this['calculated_price'],
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'group' => $this['group'],
            'subgroup' => $this['subgroup'],
            'unit_measure' => $this['unit_measure'],
            'available_actions' => $this->availableActions($status),
        ];
    }

    private function statusLabel(string $status): string
    {
        return strtoupper(trim($status)) === 'AC'
            ? 'HABILITADO'
            : 'INHABILITADO';
    }

    private function availableActions(string $status): array
    {
        $isActive = strtoupper(trim($status)) === 'AC';

        return [
            'edit' => true,
            'materials' => $isActive,
            'labor' => $isActive,
            'machinery' => $isActive,
            'files' => $isActive,
            'price_analysis' => $isActive,
            'price_recalculation' => $isActive,
            'material_breakdown' => $isActive,
            'labor_breakdown' => $isActive,
            'tools_breakdown' => $isActive,
            'breakdown_recalculation' => $isActive,
        ];
    }
}
