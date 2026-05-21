<?php

namespace App\Modules\Parameters\Services;

use App\Models\User;
use App\Modules\Security\Services\PermissionResolverService;

class ParameterPermissionService
{
    public function __construct(private readonly PermissionResolverService $permissions) {}

    public function canManage(?User $user, string|array $functionNames): bool
    {
        return $this->permissions->allows($user, 'PARAMETROS', $functionNames);
    }

    public function resolve(?User $user, string|array $functionNames, bool $canDelete = false): array
    {
        $allowed = $this->canManage($user, $functionNames);

        $permissions = [
            'can_view' => $allowed,
            'can_create' => $allowed,
            'can_update' => $allowed,
        ];

        if ($canDelete) {
            $permissions['can_delete'] = $allowed;
        }

        return $permissions;
    }
}
