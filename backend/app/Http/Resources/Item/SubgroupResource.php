<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubgroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_subgrupo,
            'group_id' => $this->id_grupo,
            'description' => $this->descripcion,
            'code' => $this->codigo,
            'status' => $this->estado,
        ];
    }
}
