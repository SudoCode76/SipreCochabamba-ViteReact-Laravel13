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
        $resolver = new ItemActionResolver;

        return [
            'id_item' => $this['id_item'],
            'name' => $this['name'],
            'calculated_price' => $this['calculated_price'],
            'status' => $status,
            'status_label' => $resolver->statusLabel($status),
            'group' => $this['group'],
            'subgroup' => $this['subgroup'],
            'unit_measure' => $this['unit_measure'],
            'available_actions' => $resolver->resolve($status),
        ];
    }
}
