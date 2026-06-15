<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\DeleteInputRequest;
use App\Http\Requests\Input\StoreInputDeleteAuthorizationRequest;
use App\Models\Authorization;
use App\Models\Input;
use App\Models\InputHistory;
use App\Models\InputLog;
use App\Models\ItemInput;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InputDeletionService
{
    public function requestAuthorization(Input $input, StoreInputDeleteAuthorizationRequest $request, User $user): Authorization
    {
        return Authorization::query()->create([
            'id_elemento' => $input->id_insumo,
            'elemento' => $input->descripcion,
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'solicitante' => $user->id_usuario,
            'estado' => 'PE',
            'nro_autorizacion' => $request->filled('nro_autorizacion') ? trim($request->string('nro_autorizacion')->toString()) : null,
            'fecha' => now(),
        ]);
    }

    public function authorizationStatus(Input $input): array
    {
        $authorization = Authorization::query()
            ->where('tabla', 'insumo')
            ->where('id_elemento', $input->id_insumo)
            ->orderByDesc('id_autorizacion')
            ->first();

        if (! $authorization) {
            return [
                'status' => 'not_found',
                'usable' => false,
                'authorization' => null,
            ];
        }

        return [
            'status' => match ($authorization->estado) {
                'AP' => 'approved',
                'PE' => 'pending',
                default => 'not_usable',
            },
            'usable' => $authorization->estado === 'AP',
            'authorization' => [
                'id_autorizacion' => $authorization->id_autorizacion,
                'nro_autorizacion' => $authorization->nro_autorizacion,
                'estado' => $authorization->estado,
            ],
        ];
    }

    public function deleteImpact(Input $input): array
    {
        $items = DB::table('item_insumo')
            ->join('item', 'item.id_item', '=', 'item_insumo.id_item')
            ->where('item_insumo.id_insumo', $input->id_insumo)
            ->where('item_insumo.estado', 'AC')
            ->groupBy('item.id_item', 'item.item', 'item.estado')
            ->orderBy('item.item')
            ->get([
                'item.id_item',
                'item.item as name',
                'item.estado as status',
            ])
            ->map(fn ($item): array => [
                'id_item' => (int) $item->id_item,
                'name' => $item->name,
                'status' => $item->status,
                'status_label' => $item->status === 'AC' ? 'HABILITADO' : 'INHABILITADO',
            ])
            ->values();

        $pendingProjects = DB::table('item_insumo')
            ->join('proyecto_item', 'proyecto_item.id_item', '=', 'item_insumo.id_item')
            ->join('proyecto', 'proyecto.id_proyecto', '=', 'proyecto_item.id_proyecto')
            ->where('item_insumo.id_insumo', $input->id_insumo)
            ->where('item_insumo.estado', 'AC')
            ->where('proyecto_item.estado', 'AC')
            ->where('proyecto.estado', 'AC')
            ->where('proyecto.aprobado', 'PD')
            ->where(function ($query): void {
                $query->where('proyecto.es_plantilla', false)
                    ->orWhereNull('proyecto.es_plantilla');
            })
            ->groupBy('proyecto.id_proyecto', 'proyecto.nombre_proyecto', 'proyecto.aprobado', 'proyecto.estado')
            ->orderBy('proyecto.nombre_proyecto')
            ->get([
                'proyecto.id_proyecto',
                'proyecto.nombre_proyecto as name',
                'proyecto.aprobado as approval_status',
                'proyecto.estado as status',
                DB::raw('COUNT(DISTINCT proyecto_item.id_item) as items_count'),
            ])
            ->map(fn ($project): array => [
                'id_proyecto' => (int) $project->id_proyecto,
                'name' => $project->name,
                'approval_status' => $project->approval_status,
                'status' => $project->status,
                'items_count' => (int) $project->items_count,
            ])
            ->values();

        return [
            'items' => $items->all(),
            'pending_projects' => $pendingProjects->all(),
            'summary' => [
                'items_count' => $items->count(),
                'pending_projects_count' => $pendingProjects->count(),
            ],
        ];
    }

    public function delete(Input $input, DeleteInputRequest $request, User $user): Input
    {
        return DB::transaction(function () use ($input, $request, $user): Input {
            $this->ensureInputCanBeDeleted($input, $request->string('autorizacion')->toString());

            $input->update([
                'estado' => 'DP',
            ]);

            InputLog::query()->create([
                'descripcion' => $input->descripcion,
                'id_insumo' => $input->id_insumo,
                'precio' => $input->precio,
                'tipo' => $input->tipo,
                'id_categoria' => $input->id_categoria,
                'unidad_medida' => $input->unidad_medida,
                'accion' => 'MD',
                'usuario' => $user->id_usuario,
                'fecha' => now()->toDateString(),
                'estado' => 'DP',
            ]);

            $this->registerHistory($input, $user, $request->ip(), 'ELIMINADO');

            $this->registerAudit($user, $request->ip(), 'Eliminacion logica de insumo '.$input->descripcion);

            return $input->refresh();
        });
    }

    public function deleteAfterApprovedAuthorization(Input $input, Authorization $authorization, User $user, ?string $ip): Input
    {
        return DB::transaction(function () use ($input, $authorization, $user, $ip): Input {
            $isInputAuthorization = strtolower((string) $authorization->tabla) === 'insumo'
                || strtolower((string) $authorization->tipo_elemento) === 'insumo';

            if (! $isInputAuthorization || (int) $authorization->id_elemento !== (int) $input->id_insumo) {
                throw ValidationException::withMessages([
                    'authorization' => ['La autorizacion no corresponde al insumo seleccionado.'],
                ]);
            }

            $input->update([
                'estado' => 'DP',
            ]);

            InputLog::query()->create([
                'descripcion' => $input->descripcion,
                'id_insumo' => $input->id_insumo,
                'precio' => $input->precio,
                'tipo' => $input->tipo,
                'id_categoria' => $input->id_categoria,
                'unidad_medida' => $input->unidad_medida,
                'accion' => 'MD',
                'usuario' => $user->id_usuario,
                'fecha' => now()->toDateString(),
                'estado' => 'DP',
            ]);

            $this->registerHistory($input, $user, $ip, 'ELIMINADO');

            $this->registerAudit(
                $user,
                $ip,
                'Autorizacion aprobada: se elimino el insumo '.$input->descripcion.' del catalogo; los proyectos existentes conservan sus datos registrados'
            );

            return $input->refresh();
        });
    }

    private function ensureInputCanBeDeleted(Input $input, string $authorizationCode): void
    {
        $hasActiveItemUsage = ItemInput::query()
            ->where('id_insumo', $input->id_insumo)
            ->where('estado', 'AC')
            ->exists();

        if ($hasActiveItemUsage) {
            throw ValidationException::withMessages([
                'input' => ['El insumo no puede eliminarse porque esta asociado a items activos.'],
            ]);
        }

        $authorizationExists = Authorization::query()
            ->where('id_elemento', $input->id_insumo)
            ->where('nro_autorizacion', trim($authorizationCode))
            ->where('tabla', 'insumo')
            ->where('estado', 'AP')
            ->exists();

        if (! $authorizationExists) {
            throw ValidationException::withMessages([
                'autorizacion' => ['No existe una autorizacion aprobada valida para eliminar este insumo.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }

    private function registerHistory(Input $input, User $user, ?string $ip, string $action): InputHistory
    {
        return InputHistory::query()->create([
            'descripcion' => $input->descripcion,
            'id_insumo' => $input->id_insumo,
            'precio' => $input->precio,
            'tipo' => $input->tipo,
            'id_categoria' => $input->id_categoria,
            'unidad_medida' => $input->unidad_medida,
            'accion' => $action,
            'usuario' => $user->id_usuario,
            'fecha' => now(),
            'estado' => $input->estado,
            'ip' => $ip,
            'nombre_usuario' => $user->funcionario,
        ]);
    }
}
