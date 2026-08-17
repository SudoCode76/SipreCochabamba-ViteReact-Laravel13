<?php

namespace App\Services\InputRequests;

use App\Services\Repository\RepositoryFileService;
use Illuminate\Http\UploadedFile;

class InputRequestFileService
{
    public function __construct(
        private readonly RepositoryFileService $repositoryFiles,
    ) {}

    public function upload(?UploadedFile $file, string $field): ?array
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return [
            'source' => $file,
            'upload' => $this->repositoryFiles->upload($file, ['document_type' => $field]),
        ];
    }
}
