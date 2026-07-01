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
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
