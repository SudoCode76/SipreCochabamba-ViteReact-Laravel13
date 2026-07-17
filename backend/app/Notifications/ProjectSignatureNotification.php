<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectSignatureNotification extends Notification
{
    use Queueable;

    public const ASSIGNED = 'project_signature_assigned';

    public const REMOVED = 'project_signature_removed';

    public const PENDING = 'project_signature_pending';

    public function __construct(private readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
