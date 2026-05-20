<?php

namespace App\Modules\Projects\Services;

use App\Models\User;

class ProjectContextService
{
    public function __construct(
        private readonly ProjectPermissionService $permissionService,
    ) {}

    public function execute(User $user): array
    {
        $people = User::query()
            ->where('estado', 'AC')
            ->orderByDesc('funcionario')
            ->get(['id_usuario', 'funcionario', 'username', 'estado']);

        $peopleOptions = $people->map(fn (User $person): array => [
            'id_usuario' => $person->id_usuario,
            'funcionario' => $person->funcionario,
            'username' => $person->username,
            'estado' => $person->estado,
        ])->values()->all();

        return [
            'people' => $people->map(fn (User $person): array => [
                'id' => $person->id_usuario,
                'full_name' => $person->funcionario,
                'username' => $person->username,
                'status' => $person->estado,
            ])->values()->all(),
            'responsible_options' => $peopleOptions,
            'requester_options' => $peopleOptions,
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'conditions' => [
                ['code' => 'PD', 'label' => 'PENDIENTE'],
                ['code' => 'RV', 'label' => 'REVISADO'],
                ['code' => 'AP', 'label' => 'APROBADO'],
            ],
            'approval_statuses' => [
                ['code' => 'PD', 'label' => 'PENDIENTE'],
                ['code' => 'RV', 'label' => 'REVISADO'],
                ['code' => 'AP', 'label' => 'APROBADO'],
            ],
            'permissions' => $this->permissionService->resolve($user),
            'metadata' => [
                'creator_user_id' => $user->id_usuario,
                'location_fields' => ['latitud', 'longitud', 'distrito', 'zona', 'otb', 'ubicacion'],
                'defaults' => [
                    'estado' => 'AC',
                    'aprobado' => 'PD',
                ],
            ],
        ];
    }
}
