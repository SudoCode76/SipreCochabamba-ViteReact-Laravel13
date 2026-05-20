<?php

namespace App\Modules\Items\Services;

use App\Http\Requests\Item\StoreCalculationPercentageRequest;
use App\Http\Requests\Item\UpdateCalculationPercentageRequest;
use App\Models\PromanCalculationPercentage;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class PromanCalculationPercentageService
{
    public function __construct(private readonly CalculationFormatPermissionService $permissions) {}

    public function context(User $user): array
    {
        return [
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => $this->permissions->resolve($user, 'PROMAN'),
            'filters' => ['search', 'description', 'code', 'status', 'page', 'per_page'],
            'endpoints' => [
                'list' => '/api/v1/calculation-percentages/proman',
                'show' => '/api/v1/calculation-percentages/proman/{id}',
                'create' => '/api/v1/calculation-percentages/proman',
                'update' => '/api/v1/calculation-percentages/proman/{id}',
            ],
        ];
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = PromanCalculationPercentage::query();

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);

            $query->where(function ($query) use ($search): void {
                $query->where('codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhere('observacion', 'like', "%{$search}%");
            });
        }

        if (filled($filters['description'] ?? null)) {
            $query->where('descripcion', 'like', '%'.trim((string) $filters['description']).'%');
        }

        if (filled($filters['code'] ?? null)) {
            $query->where('codigo', 'like', '%'.trim((string) $filters['code']).'%');
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('estado', strtoupper(trim((string) $filters['status'])));
        }

        return $query
            ->orderByDesc('id_porcentaje')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function create(StoreCalculationPercentageRequest $request, User $user): PromanCalculationPercentage
    {
        $code = trim($request->string('codigo')->toString());
        $description = trim($request->string('descripcion')->toString());

        $this->ensureUniqueCode($code);
        $this->ensureUniqueDescription($description);

        $percentage = PromanCalculationPercentage::query()->create([
            'codigo' => $code,
            'descripcion' => $description,
            'porcentaje' => (float) $request->input('porcentaje'),
            'observacion' => $request->filled('observacion') ? trim($request->string('observacion')->toString()) : null,
            'estado' => strtoupper($request->string('estado')->toString()),
            'usuario' => $user->id_usuario,
        ]);

        $this->registerAudit($user, $request->ip(), 'Registro de porcentaje de calculo PROMAN '.$percentage->descripcion);

        return $percentage;
    }

    public function update(UpdateCalculationPercentageRequest $request, PromanCalculationPercentage $percentage, User $user): PromanCalculationPercentage
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

        $this->registerAudit($user, $request->ip(), 'Actualizacion de porcentaje de calculo PROMAN '.$percentage->descripcion);

        return $percentage->refresh();
    }

    private function ensureUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $query = PromanCalculationPercentage::query()
            ->where('codigo', $code);

        if ($ignoreId !== null) {
            $query->where('id_porcentaje', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'codigo' => ['Ya existe un porcentaje de calculo PROMAN con el mismo codigo.'],
            ]);
        }
    }

    private function ensureUniqueDescription(string $description, ?int $ignoreId = null): void
    {
        $query = PromanCalculationPercentage::query()
            ->where('descripcion', $description);

        if ($ignoreId !== null) {
            $query->where('id_porcentaje', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'descripcion' => ['Ya existe un porcentaje de calculo PROMAN con la misma descripcion.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}
