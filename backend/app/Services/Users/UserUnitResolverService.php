<?php

namespace App\Services\Users;

use App\Models\Unit;

class UserUnitResolverService
{
    public function resolveId(?int $unitId, ?string $unitDescription): ?int
    {
        if ($unitId !== null) {
            return $unitId;
        }

        $description = is_string($unitDescription) ? $this->normalizeForStorage($unitDescription) : null;

        if ($description === null || $description === '') {
            return null;
        }

        $unit = Unit::query()
            ->get(['id_unidad', 'descripcion'])
            ->first(fn (Unit $unit): bool => $this->normalizeForComparison((string) $unit->descripcion) === $this->normalizeForComparison($description));

        if ($unit instanceof Unit) {
            return $unit->id_unidad;
        }

        $unit = Unit::query()->create([
            'descripcion' => $description,
            'estado' => 'AC',
        ]);

        return $unit->id_unidad;
    }

    private function normalizeForStorage(string $description): string
    {
        return preg_replace('/\s+/', ' ', trim($description)) ?? trim($description);
    }

    private function normalizeForComparison(string $description): string
    {
        return mb_strtoupper($this->normalizeForStorage($description));
    }
}
