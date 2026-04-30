<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemFndrListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_item' => $this['id_item'],
            'name' => $this['name'],
            'calculated_price' => $this['calculated_price'],
            'status' => $this['status'],
            'group' => $this['group'],
            'subgroup' => $this['subgroup'],
            'unit_measure' => $this['unit_measure'],
        ];
    }
}
