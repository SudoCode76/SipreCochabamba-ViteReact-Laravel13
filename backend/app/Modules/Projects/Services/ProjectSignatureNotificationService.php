<?php

namespace App\Modules\Projects\Services;

use App\Events\UserNotificationsChanged;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectSignatureNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProjectSignatureNotificationService
{
    public function syncAssignments(
        Project $project,
        User $actor,
        Collection $addedIds,
        Collection $removedIds
    ): void {
        $project->refresh();

        User::query()->whereIn('id_usuario', $removedIds)->get()->each(function (User $user) use ($project, $actor): void {
            $this->resolveProjectNotifications($user, (int) $project->id_proyecto);

            if ((int) $user->id_usuario === (int) $actor->id_usuario) {
                return;
            }

            $this->send($user, $this->projectPayload(
                ProjectSignatureNotification::REMOVED,
                $project,
                $actor,
                'Designación de firma retirada',
                'Ya no estás designado para firmar esta versión del proyecto.',
                'none'
            ));
        });

        User::query()
            ->whereIn('id_usuario', $addedIds)
            ->where('id_usuario', '!=', $actor->id_usuario)
            ->get()
            ->each(function (User $user) use ($project, $actor): void {
                $payload = $this->projectPayload(
                    ProjectSignatureNotification::ASSIGNED,
                    $project,
                    $actor,
                    'Firma física asignada',
                    "Tu firma física será incluida automáticamente en «{$project->nombre_proyecto}».",
                    'project'
                );

                if (! $this->hasContextNotification($user, $payload['context_key'])) {
                    $this->send($user, $payload);
                }
            });
    }

    public function notifyFinalized(Project $project, User $actor): void
    {
        $signerIds = DB::table('project_version_signature_users')
            ->where('id_proyecto', $project->id_proyecto)
            ->pluck('id_usuario');

        User::query()
            ->whereIn('id_usuario', $signerIds)
            ->where('id_usuario', '!=', $actor->id_usuario)
            ->get()
            ->each(function (User $user) use ($project, $actor): void {
                $payload = $this->projectPayload(
                    ProjectSignatureNotification::PENDING,
                    $project,
                    $actor,
                    'Firma de Ciudadanía Digital pendiente',
                    "«{$project->nombre_proyecto}» fue finalizado y requiere tu firma digital.",
                    'project'
                );

                if (! $this->hasContextNotification($user, $payload['context_key'])) {
                    $this->send($user, $payload);
                }
            });
    }

    public function serialize(DatabaseNotification $notification, User $user): array
    {
        $data = $notification->data;
        $needsSignatureImage = blank($user->firma_imagen_path)
            || ! Storage::disk('public')->exists($user->firma_imagen_path);
        $kind = (string) ($data['kind'] ?? '');
        $message = (string) ($data['message'] ?? '');

        if ($needsSignatureImage && in_array($kind, [ProjectSignatureNotification::ASSIGNED, ProjectSignatureNotification::PENDING], true)) {
            $message = "Carga tu firma física para incluirla en «{$data['project_name']}».";
        }

        return [
            'id' => $notification->id,
            'type' => $kind,
            'title' => $kind === ProjectSignatureNotification::ASSIGNED && $needsSignatureImage
                ? 'Firma física requerida'
                : ($data['title'] ?? 'Notificación'),
            'message' => $message,
            'project_id' => $data['project_id'] ?? null,
            'project_name' => $data['project_name'] ?? null,
            'version_number' => $data['version_number'] ?? null,
            'report_key' => $data['report_key'] ?? null,
            'report_name' => $data['report_name'] ?? null,
            'parameters' => $data['parameters'] ?? [],
            'needs_signature_image' => $needsSignatureImage,
            'action_kind' => in_array($kind, [ProjectSignatureNotification::ASSIGNED, ProjectSignatureNotification::PENDING], true)
                && $needsSignatureImage
                ? 'profile'
                : ($data['action_kind'] ?? 'none'),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    public function broadcastChanged(User|int $user): void
    {
        $userId = $user instanceof User ? (int) $user->id_usuario : $user;
        DB::afterCommit(function () use ($userId): void {
            try {
                UserNotificationsChanged::dispatch($userId);
            } catch (Throwable $exception) {
                Log::warning('No se pudo emitir el cambio de notificaciones.', [
                    'user_id' => $userId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function projectPayload(
        string $kind,
        Project $project,
        User $actor,
        string $title,
        string $message,
        string $actionKind
    ): array {
        return [
            'kind' => $kind,
            'title' => $title,
            'message' => $message,
            'project_id' => (int) $project->id_proyecto,
            'project_name' => $project->nombre_proyecto,
            'version_number' => (int) ($project->numero_version ?? 1),
            'actor_id' => (int) $actor->id_usuario,
            'actor_name' => $actor->funcionario,
            'action_kind' => $actionKind,
            'context_key' => "project:{$project->id_proyecto}:{$kind}",
        ];
    }

    private function send(User $user, array $payload): void
    {
        $user->notify(new ProjectSignatureNotification($payload));
        $this->broadcastChanged($user);
    }

    private function hasContextNotification(User $user, string $contextKey): bool
    {
        return $this->signatureNotifications($user)
            ->contains(fn (DatabaseNotification $notification): bool => ($notification->data['context_key'] ?? null) === $contextKey);
    }

    private function resolveProjectNotifications(User $user, int $projectId): void
    {
        $changed = false;

        $this->signatureNotifications($user, true)->each(function (DatabaseNotification $notification) use ($projectId, &$changed): void {
            $data = $notification->data;

            if ((int) ($data['project_id'] ?? 0) === $projectId
                && in_array($data['kind'] ?? null, [ProjectSignatureNotification::ASSIGNED, ProjectSignatureNotification::PENDING], true)) {
                $notification->markAsRead();
                $changed = true;
            }
        });

        if ($changed) {
            $this->broadcastChanged($user);
        }
    }

    private function signatureNotifications(User $user, bool $unreadOnly = false): Collection
    {
        // ponytail: signer lists are small; add indexed context columns only if notification volume proves this scan expensive.
        return $user->notifications()
            ->where('type', ProjectSignatureNotification::class)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->get();
    }
}
