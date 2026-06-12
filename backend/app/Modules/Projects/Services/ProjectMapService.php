<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;

class ProjectMapService
{
    public function execute(): array
    {
        $items = [];
        $skipped = 0;

        Project::query()
            ->select([
                'id_proyecto',
                'nombre_proyecto',
                'ubicacion',
                'distrito',
                'zona',
                'otb',
                'aprobado',
                'latitud',
                'longitud',
                'numero_version',
            ])
            ->where('estado', 'AC')
            ->where(function ($query): void {
                $query->where('es_plantilla', false)
                    ->orWhereNull('es_plantilla');
            })
            ->where(function ($query): void {
                $query->where('es_version_actual', true)
                    ->orWhereNull('es_version_actual');
            })
            ->orderBy('id_proyecto')
            ->chunkById(500, function ($projects) use (&$items, &$skipped): void {
                foreach ($projects as $project) {
                    $coordinateSystem = $this->coordinateSystem($project);

                    if ($coordinateSystem === null) {
                        $skipped++;

                        continue;
                    }

                    $items[] = [
                        'id' => $project->id_proyecto,
                        'name' => $project->nombre_proyecto,
                        'location' => $project->ubicacion,
                        'district' => $project->distrito,
                        'zone' => $project->zona,
                        'otb' => $project->otb,
                        'approval_status' => $project->aprobado,
                        'latitude' => $project->latitud,
                        'longitude' => $project->longitud,
                        'coordinate_system' => $coordinateSystem,
                        'version_number' => (int) ($project->numero_version ?? 1),
                    ];
                }
            }, 'id_proyecto');

        return [
            'items' => $items,
            'meta' => [
                'total' => count($items),
                'skipped' => $skipped,
            ],
        ];
    }

    private function coordinateSystem(Project $project): ?string
    {
        if (! is_numeric($project->latitud) || ! is_numeric($project->longitud)) {
            return null;
        }

        $latitude = (float) $project->latitud;
        $longitude = (float) $project->longitud;

        if ($latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
            return 'geographic';
        }

        // Legacy SIPRE stores UTM northing in latitud and easting in longitud.
        if ($latitude >= 0 && $latitude <= 10000000 && $longitude >= 100000 && $longitude <= 900000) {
            return 'utm_32719';
        }

        return null;
    }
}
