<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Projects\Services\ProjectSignatureNotificationService;
use App\Notifications\ProjectSignatureNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(private readonly ProjectSignatureNotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['all', 'unread'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $status = $validated['status'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? 10);
        $query = $user->notifications()
            ->where('type', ProjectSignatureNotification::class)
            ->when($status === 'unread', fn ($builder) => $builder->whereNull('read_at'))
            ->latest();
        $paginator = $query->paginate($perPage);
        $unreadCount = $user->unreadNotifications()
            ->where('type', ProjectSignatureNotification::class)
            ->count();

        return ApiResponse::success([
            'items' => collect($paginator->items())
                ->map(fn ($notification): array => $this->notificationService->serialize($notification, $user))
                ->values(),
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Notificaciones obtenidas correctamente.');
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $user->unreadNotifications()
            ->where('type', ProjectSignatureNotification::class)
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            $this->notificationService->broadcastChanged($user);
        }

        return ApiResponse::success(['updated' => $updated], 'Notificaciones marcadas como vistas.');
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $record = $user->notifications()
            ->where('type', ProjectSignatureNotification::class)
            ->findOrFail($notification);

        if (! $record->read_at) {
            $record->markAsRead();
            $this->notificationService->broadcastChanged($user);
        }

        return ApiResponse::success(
            ['notification' => $this->notificationService->serialize($record->refresh(), $user)],
            'Notificación marcada como vista.'
        );
    }
}
