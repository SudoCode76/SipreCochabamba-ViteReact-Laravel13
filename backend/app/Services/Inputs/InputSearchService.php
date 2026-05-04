<?php

namespace App\Services\Inputs;

use App\Models\Input;
use App\Models\UnitMeasure;
use Illuminate\Support\Str;

class InputSearchService
{
    public function searchInputs(string $search): array
    {
        $normalizedSearch = Str::lower(trim($search));

        $query = Input::query()
            ->where('estado', 'AC');

        if ($normalizedSearch !== '') {
            $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$normalizedSearch}%"]);
        }

        return $query
            ->orderBy('descripcion')
            ->limit(20)
            ->get(['id_insumo', 'descripcion'])
            ->map(fn (Input $input): array => [
                'id' => $input->id_insumo,
                'text' => $input->descripcion,
            ])
            ->values()
            ->all();
    }

    public function searchUnitMeasures(string $search): array
    {
        $normalizedSearch = Str::lower(trim($search));

        $query = UnitMeasure::query()
            ->where('estado', 'AC');

        if ($normalizedSearch !== '') {
            $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$normalizedSearch}%"]);
        }

        return $query
            ->orderBy('descripcion')
            ->limit(20)
            ->get(['id_unidad_medida', 'descripcion', 'abreviatura'])
            ->map(fn (UnitMeasure $unitMeasure): array => [
                'id' => $unitMeasure->id_unidad_medida,
                'text' => trim($unitMeasure->descripcion.' '.($unitMeasure->abreviatura ? "({$unitMeasure->abreviatura})" : '')),
            ])
            ->values()
            ->all();
    }
}
