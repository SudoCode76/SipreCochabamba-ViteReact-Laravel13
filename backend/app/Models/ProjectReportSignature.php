<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReportSignature extends Model
{
    protected $table = 'project_report_signatures';

    protected $fillable = [
        'id_proyecto',
        'id_item',
        'trace_id',
        'report_key',
        'parameters',
        'parameters_hash',
        'status',
        'code',
        'id_usuario',
        'base_file_path',
        'base_document_hash',
        'signed_file_path',
        'request_payload',
        'response_payload',
        'external_endpoint',
        'external_http_status',
        'external_request_payload',
        'external_response_payload',
        'external_error_type',
        'external_phase',
        'error_message',
        'sent_at',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'external_request_payload' => 'array',
            'external_response_payload' => 'array',
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
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
