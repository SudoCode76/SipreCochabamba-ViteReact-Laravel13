<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReportPhysicalSignature extends Model
{
    protected $fillable = [
        'id_proyecto',
        'id_item',
        'report_key',
        'parameters',
        'parameters_hash',
        'logical_document_hash',
        'id_usuario',
        'signature_image_path',
        'page_scope',
        'selected_pages',
        'page_positions',
        'pages_confirmed_at',
        'page',
        'x',
        'y',
        'width',
        'height',
        'marked_at',
    ];

    protected function casts(): array
    {
        return [
            'marked_at' => 'datetime',
            'parameters' => 'array',
            'selected_pages' => 'array',
            'page_positions' => 'array',
            'pages_confirmed_at' => 'datetime',
            'page' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id_proyecto', 'id_proyecto');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'id_item', 'id_item');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
