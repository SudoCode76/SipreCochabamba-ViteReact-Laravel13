<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepositoryFile extends Model
{
    protected $table = 'repository_files';

    protected $fillable = [
        'repository_id',
        'url_file',
        'original_name',
        'mime_type',
        'size_bytes',
        'collector',
        'system_id',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'response_payload' => 'array',
        ];
    }

    public function links(): HasMany
    {
        return $this->hasMany(RepositoryFileLink::class, 'repository_file_id');
    }
}
