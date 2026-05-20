<?php

namespace App\Services\Inputs;

use App\Models\User;
use App\Services\Permissions\PermissionResolverService;

class InputPermissionService
{
    public function __construct(private readonly PermissionResolverService $permissions) {}

    public function allows(?User $user, string|array $functionNames): bool
    {
        return $this->permissions->allows($user, 'INSUMO', $functionNames);
    }
}
