<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectReportPhysicalSignature;
use App\Models\ProjectReportSignature;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectSignatureAccessService
{
    public const MODE_ALL = 'all';

    public const MODE_SELECTED = 'selected';

    public function __construct(
        private readonly ProjectHistoryService $historyService,
        private readonly AuditService $auditService,
    ) {}

    public function ensureDefaults(Project $project): void
    {
        $root = $this->rootProject($project);

        if (! in_array($root->signature_access_mode, [self::MODE_ALL, self::MODE_SELECTED], true)) {
            $root->forceFill(['signature_access_mode' => self::MODE_SELECTED])->save();
        }

        if ($root->id_usuario) {
            DB::table('project_signature_authorized_users')->insertOrIgnore([
                'id_proyecto_raiz' => $root->id_proyecto,
                'id_usuario' => $root->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function initialize(Project $project, User $creator, ?array $configuration): void
    {
        $mode = (string) ($configuration['mode'] ?? self::MODE_SELECTED);
        $requestedIds = collect($configuration['user_ids'] ?? [$creator->id_usuario])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($mode === self::MODE_SELECTED && ! $requestedIds->contains((int) $creator->id_usuario)) {
            throw ValidationException::withMessages([
                'signature_access.user_ids' => ['El creador original del proyecto debe permanecer autorizado.'],
            ]);
        }

        $requestedIds = $requestedIds->push((int) $creator->id_usuario)->unique()->values();
        $root = $this->rootProject($project);
        $root->forceFill(['signature_access_mode' => $mode])->save();

        DB::table('project_signature_authorized_users')
            ->where('id_proyecto_raiz', $root->id_proyecto)
            ->delete();

        DB::table('project_signature_authorized_users')->insert(
            $requestedIds->map(fn (int $userId): array => [
                'id_proyecto_raiz' => $root->id_proyecto,
                'id_usuario' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );
    }

    public function configuration(Project $project, ?User $user): array
    {
        $this->ensureDefaults($project);
        $root = $this->rootProject($project)->loadMissing('creator');
        $authorizedUsers = User::query()
            ->whereIn('id_usuario', $this->authorizedUserIds($root))
            ->orderBy('funcionario')
            ->get();

        return [
            'root_project_id' => $root->id_proyecto,
            'mode' => $root->signature_access_mode ?: self::MODE_SELECTED,
            'creator' => $root->creator ? $this->serializeUser($root->creator, true) : null,
            'authorized_users' => $authorizedUsers
                ->map(fn (User $authorized): array => $this->serializeUser($authorized, $authorized->id_usuario === $root->id_usuario))
                ->values()
                ->all(),
            'can_manage' => $this->canManage($project, $user),
            'current_user_allowed' => $this->allows($project, $user),
        ];
    }

    public function decision(Project $project, ?User $user): array
    {
        $this->ensureDefaults($project);
        $root = $this->rootProject($project);
        $mode = $root->signature_access_mode ?: self::MODE_SELECTED;
        $allowed = $this->allows($project, $user);

        return [
            'mode' => $mode,
            'allowed' => $allowed,
            'message' => $allowed ? null : 'No está incluido entre las personas autorizadas para firmar este proyecto.',
        ];
    }

    public function canManage(Project $project, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $root = $this->rootProject($project);

        return $user->isAdministrator() || $root->id_usuario === $user->id_usuario;
    }

    public function allows(Project $project, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $root = $this->rootProject($project);

        if (($root->signature_access_mode ?: self::MODE_SELECTED) === self::MODE_ALL) {
            return true;
        }

        return DB::table('project_signature_authorized_users')
            ->where('id_proyecto_raiz', $root->id_proyecto)
            ->where('id_usuario', $user->id_usuario)
            ->exists();
    }

    public function update(Project $project, User $actor, string $mode, array $userIds, bool $confirmSignedRemovals, ?string $ip): array
    {
        if (! $this->canManage($project, $actor)) {
            throw new AuthorizationException('Solo el creador del proyecto o un administrador puede cambiar esta configuración.');
        }

        $this->ensureDefaults($project);
        $root = $this->rootProject($project);
        $previousMode = $root->signature_access_mode ?: self::MODE_SELECTED;
        $currentIds = $this->authorizedUserIds($root);
        $requestedIds = collect($userIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($mode === self::MODE_SELECTED && ! $requestedIds->contains((int) $root->id_usuario)) {
            throw ValidationException::withMessages([
                'user_ids' => ['El creador original del proyecto debe permanecer autorizado.'],
            ]);
        }

        $removedIds = match (true) {
            $mode !== self::MODE_SELECTED => collect(),
            $previousMode === self::MODE_ALL => $this->signedUserIds($root)->diff($requestedIds)->values(),
            default => $currentIds->diff($requestedIds)->values(),
        };
        $affectedUsers = $this->signedUsers($root, $removedIds->all());

        if ($affectedUsers !== [] && ! $confirmSignedRemovals) {
            return [
                'requires_confirmation' => true,
                'affected_users' => $affectedUsers,
            ];
        }

        DB::transaction(function () use ($root, $mode, $currentIds, $requestedIds): void {
            $root->forceFill(['signature_access_mode' => $mode])->save();

            if ($mode !== self::MODE_SELECTED) {
                return;
            }

            DB::table('project_signature_authorized_users')
                ->where('id_proyecto_raiz', $root->id_proyecto)
                ->whereIn('id_usuario', $currentIds->diff($requestedIds)->all())
                ->delete();

            foreach ($requestedIds->diff($currentIds) as $userId) {
                DB::table('project_signature_authorized_users')->insert([
                    'id_proyecto_raiz' => $root->id_proyecto,
                    'id_usuario' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $addedIds = $mode === self::MODE_SELECTED ? $requestedIds->diff($currentIds)->values()->all() : [];
        $removed = $mode === self::MODE_SELECTED ? $currentIds->diff($requestedIds)->values()->all() : [];
        $this->historyService->recordSignatureAccessUpdated($project, $actor, $ip, [
            'previous_mode' => $previousMode,
            'mode' => $mode,
            'added_users' => $this->userSummaries($addedIds),
            'removed_users' => $this->userSummaries($removed),
        ]);
        $this->auditService->record($actor, $ip, 'PROYECTOS: se actualizó la configuración de firmantes de '.$root->nombre_proyecto);

        return [
            'requires_confirmation' => false,
            'configuration' => $this->configuration($project, $actor),
        ];
    }

    private function rootProject(Project $project): Project
    {
        $rootId = (int) ($project->id_proyecto_raiz ?: $project->id_proyecto);

        return $project->id_proyecto === $rootId
            ? ($project->fresh() ?: $project)
            : Project::query()->findOrFail($rootId);
    }

    private function authorizedUserIds(Project $root): Collection
    {
        return DB::table('project_signature_authorized_users')
            ->where('id_proyecto_raiz', $root->id_proyecto)
            ->pluck('id_usuario')
            ->map(fn ($id): int => (int) $id);
    }

    private function userSummaries(array $userIds): array
    {
        return User::query()
            ->whereIn('id_usuario', $userIds)
            ->orderBy('funcionario')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id_usuario,
                'full_name' => $user->funcionario,
            ])
            ->all();
    }

    private function signedUsers(Project $root, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $projectIds = Project::query()
            ->where('id_proyecto', $root->id_proyecto)
            ->orWhere('id_proyecto_raiz', $root->id_proyecto)
            ->pluck('id_proyecto');
        $digital = ProjectReportSignature::query()
            ->whereIn('id_proyecto', $projectIds)
            ->whereIn('id_usuario', $userIds)
            ->where('status', 'signed')
            ->selectRaw('id_usuario, COUNT(*) as total')
            ->groupBy('id_usuario')
            ->pluck('total', 'id_usuario');
        $physical = ProjectReportPhysicalSignature::query()
            ->whereIn('id_proyecto', $projectIds)
            ->whereIn('id_usuario', $userIds)
            ->selectRaw('id_usuario, COUNT(*) as total')
            ->groupBy('id_usuario')
            ->pluck('total', 'id_usuario');

        return User::query()
            ->whereIn('id_usuario', $userIds)
            ->get()
            ->map(function (User $user) use ($digital, $physical): array {
                $digitalCount = (int) ($digital[$user->id_usuario] ?? 0);
                $physicalCount = (int) ($physical[$user->id_usuario] ?? 0);

                return [
                    'id' => $user->id_usuario,
                    'full_name' => $user->funcionario,
                    'digital_signatures' => $digitalCount,
                    'physical_signatures' => $physicalCount,
                    'total_signatures' => $digitalCount + $physicalCount,
                ];
            })
            ->filter(fn (array $user): bool => $user['total_signatures'] > 0)
            ->values()
            ->all();
    }

    private function signedUserIds(Project $root): Collection
    {
        $projectIds = Project::query()
            ->where('id_proyecto', $root->id_proyecto)
            ->orWhere('id_proyecto_raiz', $root->id_proyecto)
            ->pluck('id_proyecto');

        return ProjectReportSignature::query()
            ->whereIn('id_proyecto', $projectIds)
            ->where('status', 'signed')
            ->pluck('id_usuario')
            ->merge(
                ProjectReportPhysicalSignature::query()
                    ->whereIn('id_proyecto', $projectIds)
                    ->pluck('id_usuario')
            )
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function serializeUser(User $user, bool $isCreator): array
    {
        return [
            'id' => $user->id_usuario,
            'full_name' => $user->funcionario,
            'username' => $user->username,
            'status' => $user->estado,
            'is_creator' => $isCreator,
        ];
    }
}
