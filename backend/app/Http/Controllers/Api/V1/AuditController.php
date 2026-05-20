<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\IndexAuditRequest;
use App\Http\Resources\Audit\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AuditController extends Controller
{
    public function index(IndexAuditRequest $request): JsonResponse
    {
        $query = AuditLog::query();

        if ($request->filled('search')) {
            $search = Str::lower(trim($request->string('search')->toString()));
            $query->whereRaw('LOWER(COALESCE(proceso, \'\')) LIKE ?', ["%{$search}%"]);
        }

        if ($request->filled('user')) {
            $user = Str::lower(trim($request->string('user')->toString()));
            $query->whereRaw('LOWER(COALESCE(nombre_completo, \'\')) LIKE ?', ["%{$user}%"]);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('fecha_hora', '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('fecha_hora', '<=', $request->date('date_to')->toDateString());
        }

        $logs = $query
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_auditoria')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Auditoria obtenida correctamente.',
            'data' => [
                'items' => AuditLogResource::collection($logs->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'last_page' => $logs->lastPage(),
                    'has_more_pages' => $logs->hasMorePages(),
                ],
            ],
        ]);
    }
}
