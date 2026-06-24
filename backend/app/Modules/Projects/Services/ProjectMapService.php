<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;

class ProjectMapService
{
    private const DEFAULT_LIMIT = 500;
    private const MAX_LIMIT = 500;
    private const UTM_ZONE = 19;

    public function execute(array $filters = []): array
    {
        $items = [];
        $skipped = 0;
        $truncated = false;
        $limit = $this->limit($filters['limit'] ?? null);
        $bbox = $this->bbox($filters['bbox'] ?? null);
        $nearby = $this->nearby($filters);

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
            ->chunkById(500, function ($projects) use (&$items, &$skipped, &$truncated, $limit, $bbox, $nearby): bool {
                foreach ($projects as $project) {
                    $coordinateSystem = $this->coordinateSystem($project);

                    if ($coordinateSystem === null) {
                        $skipped++;

                        continue;
                    }

                    $position = $this->projectPosition($project, $coordinateSystem);

                    if ($position === null) {
                        $skipped++;

                        continue;
                    }

                    if ($bbox !== null && ! $this->isInsideBbox($position, $bbox)) {
                        continue;
                    }

                    $distance = null;
                    if ($nearby !== null) {
                        $distance = $this->distanceMeters($nearby['lat'], $nearby['lng'], $position['lat'], $position['lng']);

                        if ($distance > $nearby['radius']) {
                            continue;
                        }
                    }

                    if (count($items) >= $limit) {
                        $truncated = true;

                        return false;
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
                        'distance' => $distance === null ? null : round($distance, 2),
                    ];
                }

                return true;
            }, 'id_proyecto');

        return [
            'items' => $items,
            'meta' => [
                'total' => count($items),
                'total_returned' => count($items),
                'limit' => $limit,
                'truncated' => $truncated,
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

    private function limit(mixed $limit): int
    {
        if (! is_numeric($limit)) {
            return self::DEFAULT_LIMIT;
        }

        return max(1, min((int) $limit, self::MAX_LIMIT));
    }

    private function bbox(?string $bbox): ?array
    {
        if ($bbox === null || trim($bbox) === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $bbox));

        if (count($parts) !== 4 || array_filter($parts, fn ($part): bool => ! is_numeric($part)) !== []) {
            return null;
        }

        [$south, $west, $north, $east] = array_map('floatval', $parts);

        if ($south > $north || $west > $east) {
            return null;
        }

        return compact('south', 'west', 'north', 'east');
    }

    private function nearby(array $filters): ?array
    {
        if (! is_numeric($filters['lat'] ?? null) || ! is_numeric($filters['lng'] ?? null)) {
            return null;
        }

        $radius = is_numeric($filters['radius'] ?? null) ? (float) $filters['radius'] : 500.0;

        return [
            'lat' => (float) $filters['lat'],
            'lng' => (float) $filters['lng'],
            'radius' => max(1.0, min($radius, 5000.0)),
        ];
    }

    private function projectPosition(Project $project, string $coordinateSystem): ?array
    {
        $latitude = (float) $project->latitud;
        $longitude = (float) $project->longitud;

        if ($coordinateSystem === 'geographic') {
            return ['lat' => $latitude, 'lng' => $longitude];
        }

        if ($coordinateSystem === 'utm_32719') {
            return $this->utmToLatLng((float) $project->longitud, (float) $project->latitud);
        }

        return null;
    }

    private function isInsideBbox(array $position, array $bbox): bool
    {
        return $position['lat'] >= $bbox['south']
            && $position['lat'] <= $bbox['north']
            && $position['lng'] >= $bbox['west']
            && $position['lng'] <= $bbox['east'];
    }

    private function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000.0;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function utmToLatLng(float $easting, float $northing): ?array
    {
        $a = 6378137.0;
        $eccSquared = 0.00669438;
        $k0 = 0.9996;
        $eccPrimeSquared = $eccSquared / (1 - $eccSquared);
        $x = $easting - 500000.0;
        $y = $northing - 10000000.0;
        $longOrigin = (self::UTM_ZONE - 1) * 6 - 180 + 3;
        $m = $y / $k0;
        $mu = $m / ($a * (1 - $eccSquared / 4 - 3 * $eccSquared ** 2 / 64 - 5 * $eccSquared ** 3 / 256));
        $e1 = (1 - sqrt(1 - $eccSquared)) / (1 + sqrt(1 - $eccSquared));
        $phi1Rad = $mu
            + (3 * $e1 / 2 - 27 * $e1 ** 3 / 32) * sin(2 * $mu)
            + (21 * $e1 ** 2 / 16 - 55 * $e1 ** 4 / 32) * sin(4 * $mu)
            + (151 * $e1 ** 3 / 96) * sin(6 * $mu);
        $n1 = $a / sqrt(1 - $eccSquared * sin($phi1Rad) ** 2);
        $t1 = tan($phi1Rad) ** 2;
        $c1 = $eccPrimeSquared * cos($phi1Rad) ** 2;
        $r1 = $a * (1 - $eccSquared) / ((1 - $eccSquared * sin($phi1Rad) ** 2) ** 1.5);
        $d = $x / ($n1 * $k0);
        $lat = $phi1Rad - ($n1 * tan($phi1Rad) / $r1) * (
            $d ** 2 / 2
            - (5 + 3 * $t1 + 10 * $c1 - 4 * $c1 ** 2 - 9 * $eccPrimeSquared) * $d ** 4 / 24
            + (61 + 90 * $t1 + 298 * $c1 + 45 * $t1 ** 2 - 252 * $eccPrimeSquared - 3 * $c1 ** 2) * $d ** 6 / 720
        );
        $lng = deg2rad($longOrigin) + (
            $d
            - (1 + 2 * $t1 + $c1) * $d ** 3 / 6
            + (5 - 2 * $c1 + 28 * $t1 - 3 * $c1 ** 2 + 8 * $eccPrimeSquared + 24 * $t1 ** 2) * $d ** 5 / 120
        ) / cos($phi1Rad);

        $lat = rad2deg($lat);
        $lng = rad2deg($lng);

        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }
}
