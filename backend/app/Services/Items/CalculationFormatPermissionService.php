<?php

namespace App\Services\Items;

use App\Models\User;
use App\Services\Permissions\PermissionResolverService;

class CalculationFormatPermissionService
{
    public function __construct(private readonly PermissionResolverService $permissions) {}

    public function resolve(?User $user, string $functionName): array
    {
        $allowed = $this->permissions->allows($user, 'ITEMS', $functionName);

        return [
            'can_view' => $allowed,
            'can_create' => $allowed,
            'can_update' => $allowed,
        ];
    }
}
