<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'users.{userId}',
    fn (User $user, string $userId): bool => (int) $user->getAuthIdentifier() === (int) $userId,
    ['guards' => ['sanctum']]
);
