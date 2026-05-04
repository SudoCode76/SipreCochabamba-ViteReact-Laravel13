<?php

namespace App\Services\InputRequests;

use Illuminate\Http\UploadedFile;

class InputRequestFileService
{
    public function store(?UploadedFile $file, string $prefix): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store('archivos/cotizaciones/'.$prefix, 'public');
    }
}
