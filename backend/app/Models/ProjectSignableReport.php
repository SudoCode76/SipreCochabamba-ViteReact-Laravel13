<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSignableReport extends Model
{
    protected $table = 'project_signable_reports';

    protected $fillable = [
        'report_key',
        'name',
        'description',
        'is_enabled',
        'requires_finalized_project',
        'validity_days',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'requires_finalized_project' => 'boolean',
            'validity_days' => 'integer',
        ];
    }
}
