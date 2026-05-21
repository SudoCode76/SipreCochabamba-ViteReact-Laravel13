<?php

namespace App\Modules\Items\Services;

use App\Models\User;
use App\Modules\Security\Services\PermissionResolverService;

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
