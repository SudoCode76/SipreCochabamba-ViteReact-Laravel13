<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    public function record(?User $actor, ?string $ip, string $process): void
    {
        try {
            AuditLog::query()->create([
                'nombre_completo' => $actor?->funcionario,
                'fecha_hora' => now(),
                'ip' => $ip,
                'proceso' => $process,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
