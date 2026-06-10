<?php

namespace App\Services\InputRequests;

use App\Services\Files\PublicFileService;
use Illuminate\Http\UploadedFile;

class InputRequestFileService
{
    public function __construct(
        private readonly PublicFileService $publicFileService,
    ) {}

    public function store(?UploadedFile $file, string $prefix): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $this->publicFileService->storeQuote($file, $prefix);
    }
}
