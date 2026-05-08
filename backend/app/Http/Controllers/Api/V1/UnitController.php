<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UnitResource;
use App\Models\Unit;
use App\Services\Users\UnitContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(
        private readonly UnitContextService $unitContextService,
    ) {}

    public function index(): JsonResponse
    {
        $units = Unit::query()
            ->when(request()->filled('search'), function ($query): void {
                $search = mb_strtolower(trim((string) request()->query('search')));

                $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$search}%"]);
            })
            ->orderBy('descripcion')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Unidades obtenidas correctamente.',
            'data' => [
                'items' => UnitResource::collection($units)->resolve(),
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de unidades obtenido correctamente.',
            'data' => $this->unitContextService->execute($request->user()),
        ]);
    }

    public function show(Unit $unit): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Unidad obtenida correctamente.',
            'data' => [
                'unit' => UnitResource::make($unit)->resolve(),
            ],
        ]);
    }
}
