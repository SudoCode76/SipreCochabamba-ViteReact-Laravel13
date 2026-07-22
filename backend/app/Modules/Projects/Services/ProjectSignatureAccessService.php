<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectReportSignature;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectSignatureAccessService
{
    public const MODE_SELECTED = 'selected';

    public const MODE_ALL = 'all';

    public function __construct(
        private readonly ProjectHistoryService $historyService,
        private readonly AuditService $auditService,
        private readonly ProjectSignatureNotificationService $notificationService,
    ) {}

    public function ensureDefaults(Project $project): void
    {
        // Existing projects stay explicitly unconfigured until their owner chooses a finite list.
    }

    public function initialize(Project $project, User $creator, ?array $configuration): void
    {
        $ids = collect($configuration['user_ids'] ?? [$creator->id_usuario])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $this->replaceSigners($project, $ids);
        $project->forceFill([
            'signature_access_mode' => self::MODE_SELECTED,
            'signature_signers_configured_at' => now(),
            'signature_signers_locked_at' => null,
        ])->save();
        $this->notificationService->syncAssignments($project, $creator, $ids, collect());
    }

    public function inherit(Project $source, Project $target, User $creator): void
    {
        $ids = DB::table('project_version_signature_users')
            ->where('id_proyecto', $source->id_proyecto)
            ->pluck('id_usuario')
            ->map(fn ($id): int => (int) $id);

        $this->replaceSigners($target, $ids->isEmpty() ? collect([$creator->id_usuario]) : $ids);
        $target->forceFill([
            'signature_access_mode' => self::MODE_SELECTED,
            'signature_signers_configured_at' => now(),
            'signature_signers_locked_at' => null,
        ])->save();
        $this->notificationService->syncAssignments(
            $target,
            $creator,
            $ids->isEmpty() ? collect([$creator->id_usuario]) : $ids,
            collect()
        );
    }

    public function configuration(Project $project, ?User $user): array
    {
        $root = $this->rootProject($project)->loadMissing('creator');
        $signers = $this->signers($project);

        return [
            'project_id' => $project->id_proyecto,
            'root_project_id' => $root->id_proyecto,
            'mode' => self::MODE_SELECTED,
            'configured' => filled($project->signature_signers_configured_at),
            'locked' => filled($project->signature_signers_locked_at),
            'locked_at' => $project->signature_signers_locked_at?->toIso8601String(),
            'creator' => $root->creator ? $this->serializeUser($root->creator, true) : null,
            'authorized_users' => $signers->map(fn (User $signer): array => $this->serializeUser(
                $signer,
                $signer->id_usuario === $root->id_usuario,
                $signer->signature_image_path_snapshot ?? null,
            ))->values()->all(),
            'can_manage' => $this->canManage($project, $user) && blank($project->signature_signers_locked_at),
            'current_user_allowed' => $this->allows($project, $user),
        ];
    }

    public function decision(Project $project, ?User $user): array
    {
        $configured = filled($project->signature_signers_configured_at);
        $allowed = $configured && $this->allows($project, $user);

        return [
            'mode' => self::MODE_SELECTED,
            'configured' => $configured,
            'locked' => filled($project->signature_signers_locked_at),
            'allowed' => $allowed,
            'message' => match (true) {
                ! $configured => 'Debe configurar una lista de firmantes para esta versión antes de firmar.',
                ! $allowed => 'No está incluido entre las personas autorizadas para firmar esta versión.',
                default => null,
            },
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

    public function canModify(Project $project, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return ! $project->project_access_restricted
            || $user->isAdministrator()
            || $this->allows($project, $user);
    }

    public function allows(Project $project, ?User $user): bool
    {
        return $user
            && DB::table('project_version_signature_users')
                ->where('id_proyecto', $project->id_proyecto)
                ->where('id_usuario', $user->id_usuario)
                ->exists();
    }

    public function update(Project $project, User $actor, string $mode, array $userIds, bool $confirmSignedRemovals, ?string $ip): array
    {
        if (! $this->canManage($project, $actor)) {
            throw new AuthorizationException('Solo el creador del proyecto o un administrador puede cambiar esta configuración.');
        }

        if ($project->signature_signers_locked_at) {
            throw ValidationException::withMessages([
                'user_ids' => ['Los firmantes de esta versión quedaron bloqueados por su primera firma digital. Cree una nueva versión para cambiarlos.'],
            ]);
        }

        if ($this->hasPendingAttempt($project)) {
            throw ValidationException::withMessages([
                'user_ids' => ['Existe una firma digital en proceso. Cancélela antes de cambiar los firmantes.'],
            ]);
        }

        $ids = collect($userIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $previousIds = DB::table('project_version_signature_users')
            ->where('id_proyecto', $project->id_proyecto)
            ->pluck('id_usuario')
            ->map(fn ($id): int => (int) $id);
        $addedIds = $ids->diff($previousIds)->values();
        $removedIds = $previousIds->diff($ids)->values();

        $this->replaceSigners($project, $ids);
        $project->forceFill([
            'signature_access_mode' => self::MODE_SELECTED,
            'signature_signers_configured_at' => now(),
        ])->save();

        $this->historyService->recordSignatureAccessUpdated($project, $actor, $ip, [
            'mode' => self::MODE_SELECTED,
            'added_users' => $this->userSummaries($addedIds->all()),
            'removed_users' => $this->userSummaries($removedIds->all()),
        ]);
        $this->auditService->record($actor, $ip, 'PROYECTOS: se actualizó la lista de firmantes de '.$project->nombre_proyecto);
        $this->notificationService->syncAssignments($project, $actor, $addedIds, $removedIds);

        return [
            'requires_confirmation' => false,
            'configuration' => $this->configuration($project->refresh(), $actor),
        ];
    }

    public function signers(Project $project): Collection
    {
        $rows = DB::table('project_version_signature_users')
            ->where('id_proyecto', $project->id_proyecto)
            ->get()
            ->keyBy('id_usuario');

        return User::query()
            ->whereIn('id_usuario', $rows->keys())
            ->orderBy('funcionario')
            ->get()
            ->each(function (User $user) use ($rows): void {
                $row = $rows[$user->id_usuario];
                $user->setAttribute('signature_image_path_snapshot', $row->signature_image_path);
                $user->setAttribute('signature_image_hash_snapshot', $row->signature_image_hash);
            });
    }

    public function lock(Project $project, Collection $physicalSignatures): void
    {
        if ($project->signature_signers_locked_at) {
            return;
        }

        DB::transaction(function () use ($project, $physicalSignatures): void {
            foreach ($physicalSignatures as $signature) {
                $hash = Storage::disk('public')->exists($signature->signature_image_path)
                    ? hash('sha256', Storage::disk('public')->get($signature->signature_image_path))
                    : null;

                DB::table('project_version_signature_users')
                    ->where('id_proyecto', $project->id_proyecto)
                    ->where('id_usuario', $signature->id_usuario)
                    ->update([
                        'signature_image_path' => $signature->signature_image_path,
                        'signature_image_hash' => $hash,
                        'updated_at' => now(),
                    ]);
            }

            $project->forceFill(['signature_signers_locked_at' => now()])->save();
        });
    }

    public function hasPendingAttempt(Project $project): bool
    {
        return ProjectReportSignature::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->whereIn('status', ['pending', 'auth_pending', 'sent'])
            ->exists();
    }

    private function replaceSigners(Project $project, Collection $ids): void
    {
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => ['Seleccione al menos una persona firmante.'],
            ]);
        }

        $activeIds = User::query()
            ->whereIn('id_usuario', $ids)
            ->where('estado', 'AC')
            ->pluck('id_usuario')
            ->map(fn ($id): int => (int) $id);

        if ($activeIds->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'user_ids' => ['Todos los firmantes seleccionados deben ser usuarios activos.'],
            ]);
        }

        DB::transaction(function () use ($project, $ids): void {
            DB::table('project_version_signature_users')->where('id_proyecto', $project->id_proyecto)->delete();
            DB::table('project_version_signature_users')->insert($ids->map(fn (int $id): array => [
                'id_proyecto' => $project->id_proyecto,
                'id_usuario' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            if (DB::getSchemaBuilder()->hasTable('project_signature_authorized_users')) {
                $rootId = (int) ($project->id_proyecto_raiz ?: $project->id_proyecto);
                DB::table('project_signature_authorized_users')->where('id_proyecto_raiz', $rootId)->delete();
                DB::table('project_signature_authorized_users')->insert($ids->map(fn (int $id): array => [
                    'id_proyecto_raiz' => $rootId,
                    'id_usuario' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            }
        });
    }

    private function rootProject(Project $project): Project
    {
        $rootId = (int) ($project->id_proyecto_raiz ?: $project->id_proyecto);

        return $project->id_proyecto === $rootId
            ? ($project->fresh() ?: $project)
            : Project::query()->findOrFail($rootId);
    }

    private function userSummaries(array $ids): array
    {
        return User::query()->whereIn('id_usuario', $ids)->orderBy('funcionario')->get()
            ->map(fn (User $user): array => ['id' => $user->id_usuario, 'full_name' => $user->funcionario])
            ->all();
    }

    private function serializeUser(User $user, bool $isCreator, ?string $snapshotPath = null): array
    {
        $path = $snapshotPath ?: $user->firma_imagen_path;

        return [
            'id' => $user->id_usuario,
            'full_name' => $user->funcionario,
            'username' => $user->username,
            'status' => $user->estado,
            'is_creator' => $isCreator,
            'has_signature_image' => filled($path) && Storage::disk('public')->exists($path),
            'signature_image_url' => $path ? url(Storage::url($path)) : null,
        ];
    }
}
