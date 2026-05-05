<?php

namespace App\Services\Inputs;

use App\Models\Input;
use App\Models\InputType;
use App\Models\UnitMeasure;
use Illuminate\Support\Str;

class InputSearchService
{
    public function searchInputs(string $search, ?int $type = null): array
    {
        $normalizedSearch = Str::lower(trim($search));

        $query = Input::query()
            ->where('estado', 'AC');

        if ($type !== null) {
            $query->where('tipo', $type);
        }

        if ($normalizedSearch !== '') {
            $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$normalizedSearch}%"]);
        }

        return $query
            ->orderBy('descripcion')
            ->limit(20)
            ->get(['id_insumo', 'descripcion', 'tipo', 'precio'])
            ->map(fn (Input $input): array => [
                'id' => $input->id_insumo,
                'text' => $input->descripcion,
                'tipo' => $input->tipo,
                'precio' => (float) $input->precio,
            ])
            ->values()
            ->all();
    }

    public function searchInputTypes(string $search): array
    {
        $normalizedSearch = Str::lower(trim($search));

        $query = InputType::query()
            ->where('estado', 'AC');

        if ($normalizedSearch !== '') {
            $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$normalizedSearch}%"]);
        }

        return $query
            ->orderBy('descripcion')
            ->limit(20)
            ->get(['id_tipo', 'descripcion'])
            ->map(fn (InputType $inputType): array => [
                'id' => $inputType->id_tipo,
                'text' => $inputType->descripcion,
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
