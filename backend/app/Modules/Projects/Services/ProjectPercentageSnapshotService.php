<?php

namespace App\Modules\Projects\Services;

use App\Models\FndrCalculationPercentage;
use App\Models\FpsCalculationPercentage;
use App\Models\GeneralCalculationPercentage;
use App\Models\ObrasCalculationPercentage;
use App\Models\Project;
use App\Models\ProjectPercentageSnapshot;
use App\Models\PromanCalculationPercentage;
use App\Models\UpreCalculationPercentage;

class ProjectPercentageSnapshotService
{
    private const MODELS = [
        'PCA' => GeneralCalculationPercentage::class,
        'PC_OBRAS' => ObrasCalculationPercentage::class,
        'PC_FPS' => FpsCalculationPercentage::class,
        'PC_FNDR' => FndrCalculationPercentage::class,
        'PC_UPRE' => UpreCalculationPercentage::class,
        'PC_PROMAN' => PromanCalculationPercentage::class,
    ];

    public function synchronize(Project $project): void
    {
        foreach (self::MODELS as $format => $model) {
            ProjectPercentageSnapshot::query()
                ->where('id_proyecto', $project->id_proyecto)
                ->where('formato', $format)
                ->delete();

            $rows = $model::query()->active()->orderBy('id_porcentaje')->get();

            foreach ($rows as $row) {
                ProjectPercentageSnapshot::query()->create([
                    'id_proyecto' => $project->id_proyecto,
                    'formato' => $format,
                    'codigo' => $row->codigo,
                    'descripcion' => $row->descripcion,
                    'porcentaje' => $row->porcentaje,
                    'estado' => $row->estado,
                ]);
            }
        }
    }

    public function ensure(Project $project): void
    {
        if (! ProjectPercentageSnapshot::query()->where('id_proyecto', $project->id_proyecto)->exists()) {
            $this->synchronize($project);
        }
    }

    public function copy(Project $source, Project $target): void
    {
        ProjectPercentageSnapshot::query()
            ->where('id_proyecto', $source->id_proyecto)
            ->orderBy('id_snapshot')
            ->get()
            ->each(function (ProjectPercentageSnapshot $snapshot) use ($target): void {
                ProjectPercentageSnapshot::query()->create([
                    'id_proyecto' => $target->id_proyecto,
                    'formato' => $snapshot->formato,
                    'codigo' => $snapshot->codigo,
                    'descripcion' => $snapshot->descripcion,
                    'porcentaje' => $snapshot->porcentaje,
                    'estado' => $snapshot->estado,
                ]);
            });
    }
}
