<?php

namespace App\Modules\Items\Services\Analysis;

use App\Http\Requests\Item\StoreItemRequest;
use App\Models\Item;
use App\Models\User;

class CreateAnalysisItemService
{
    public function execute(StoreItemRequest $request, User $user): Item
    {
        return Item::query()->create([
            'item' => strtoupper(trim($request->string('item')->toString())),
            'id_unidad' => (int) $request->integer('unit_measure_id'),
            'precio' => null,
            'estado' => strtoupper($request->string('status')->toString()),
            'id_usuario' => $user->id_usuario,
            'cod' => $request->filled('code') ? trim($request->string('code')->toString()) : null,
            'grupo' => (int) $request->integer('group_id'),
            'subgrupo' => (int) $request->integer('subgroup_id'),
            'especificacion' => $request->filled('specification') ? trim($request->string('specification')->toString()) : null,
            'ficha' => $request->filled('sheet') ? trim($request->string('sheet')->toString()) : null,
            'fecha_item' => now()->toDateString(),
        ]);
    }
}
