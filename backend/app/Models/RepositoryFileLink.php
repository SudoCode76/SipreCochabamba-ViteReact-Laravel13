<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RepositoryFileLink extends Model
{
    protected $table = 'repository_file_links';

    protected $fillable = [
        'repository_file_id',
        'linkable_type',
        'linkable_id',
        'field',
    ];

    public function repositoryFile(): BelongsTo
    {
        return $this->belongsTo(RepositoryFile::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
