<?php

namespace App\Modules\Security\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PermissionResolverService
{
    public function allows(?User $user, string $className, string|array $functionNames): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        $normalizedFunctions = collect((array) $functionNames)
            ->map(fn (string $functionName): string => $this->normalize($functionName))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedFunctions->isEmpty()) {
            return false;
        }

        $normalizedClass = $this->normalize($className);

        return $user->activePermissions()
            ->whereHas('systemFunction', function (Builder $query) use ($normalizedClass, $normalizedFunctions): void {
                $query->whereRaw('UPPER(TRIM(clase)) = ?', [$normalizedClass])
                    ->where(function (Builder $query) use ($normalizedFunctions): void {
                        foreach ($normalizedFunctions as $functionName) {
                            $query->orWhereRaw('UPPER(TRIM(nombre_funcion)) = ?', [$functionName]);
                        }
                    });
            })
            ->exists();
    }

    public function resolveMap(?User $user, string $className, array $permissionMap): array
    {
        $permissions = [];

        foreach ($permissionMap as $key => $functionNames) {
            $permissions[$key] = $this->allows($user, $className, $functionNames);
        }

        return $permissions;
    }

    private function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }
}
