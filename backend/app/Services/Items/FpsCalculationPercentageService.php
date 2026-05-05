<?php

namespace App\Services\Items;

use App\Http\Requests\Item\StoreCalculationPercentageRequest;
use App\Http\Requests\Item\UpdateCalculationPercentageRequest;
use App\Models\FpsCalculationPercentage;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class FpsCalculationPercentageService
{
    public function context(User $user): array
    {
        return [
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => [
                'can_view' => $user->isAdministrator(),
                'can_create' => $user->isAdministrator(),
                'can_update' => $user->isAdministrator(),
            ],
        ];
    }

    public function list(): Collection
    {
        return FpsCalculationPercentage::query()
            ->orderByDesc('id_porcentaje')
            ->get();
    }

    public function create(StoreCalculationPercentageRequest $request, User $user): FpsCalculationPercentage
    {
        $code = trim($request->string('codigo')->toString());
        $description = trim($request->string('descripcion')->toString());

        $this->ensureUniqueCode($code);
        $this->ensureUniqueDescription($description);

        $percentage = FpsCalculationPercentage::query()->create([
            'codigo' => $code,
            'descripcion' => $description,
            'porcentaje' => (float) $request->input('porcentaje'),
            'observacion' => $request->filled('observacion') ? trim($request->string('observacion')->toString()) : null,
            'estado' => strtoupper($request->string('estado')->toString()),
            'usuario' => $user->id_usuario,
        ]);

        $this->registerAudit($user, $request->ip(), 'Registro de porcentaje de calculo FPS '.$percentage->descripcion);

        return $percentage;
    }

    public function update(UpdateCalculationPercentageRequest $request, FpsCalculationPercentage $percentage, User $user): FpsCalculationPercentage
    {
        $code = trim($request->string('codigo')->toString());
        $description = trim($request->string('descripcion')->toString());

        if ($code !== trim((string) $percentage->codigo)) {
            $this->ensureUniqueCode($code, $percentage->id_porcentaje);
        }

        if ($description !== trim((string) $percentage->descripcion)) {
            $this->ensureUniqueDescription($description, $percentage->id_porcentaje);
        }

        $percentage->update([
            'codigo' => $code,
            'descripcion' => $description,
            'porcentaje' => (float) $request->input('porcentaje'),
            'observacion' => $request->filled('observacion') ? trim($request->string('observacion')->toString()) : null,
            'estado' => strtoupper($request->string('estado')->toString()),
            'usuario' => $user->id_usuario,
        ]);

        $this->registerAudit($user, $request->ip(), 'Actualizacion de porcentaje de calculo FPS '.$percentage->descripcion);

        return $percentage->refresh();
    }

    private function ensureUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $query = FpsCalculationPercentage::query()
            ->where('codigo', $code);

        if ($ignoreId !== null) {
            $query->where('id_porcentaje', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'codigo' => ['Ya existe un porcentaje de calculo FPS con el mismo codigo.'],
            ]);
        }
    }

    private function ensureUniqueDescription(string $description, ?int $ignoreId = null): void
    {
        $query = FpsCalculationPercentage::query()
            ->where('descripcion', $description);

        if ($ignoreId !== null) {
            $query->where('id_porcentaje', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'descripcion' => ['Ya existe un porcentaje de calculo FPS con la misma descripcion.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}
