<?php

namespace App\Services\Repository;

readonly class RepositoryUploadResult
{
    public function __construct(
        public string $repositoryId,
        public string $fileUrl,
        public array $payload,
    ) {}
}
