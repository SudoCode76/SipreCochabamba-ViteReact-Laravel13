<?php

namespace App\Services\Repository;

use App\Models\RepositoryFile;
use App\Models\RepositoryFileLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class RepositoryFileService
{
    public function __construct(
        private readonly RepositoryClient $repositoryClient,
    ) {}

    /**
     * Uploads the document before the local transaction. A database rollback must
     * never attempt to remove metadata already accepted by the external repository.
     */
    public function upload(UploadedFile $file, array $metadata = []): RepositoryUploadResult
    {
        return $this->repositoryClient->upload($file, $metadata)[0];
    }

    public function persist(RepositoryUploadResult $upload, UploadedFile $file): RepositoryFile
    {
        return RepositoryFile::query()->firstOrCreate(
            ['repository_id' => $upload->repositoryId],
            [
                'url_file' => $upload->fileUrl,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'collector' => config('services.repository.collector'),
                'system_id' => config('services.repository.system_id'),
                'response_payload' => array_merge($upload->payload, [
                    'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                ]),
            ],
        );
    }

    public function link(RepositoryFile $file, Model $linkable, string $field): void
    {
        RepositoryFileLink::query()->firstOrCreate([
            'repository_file_id' => $file->getKey(),
            'linkable_type' => $linkable->getMorphClass(),
            'linkable_id' => $linkable->getKey(),
            'field' => $field,
        ]);
    }

    public function persistAndLink(RepositoryUploadResult $upload, UploadedFile $source, Model $linkable, string $field): string
    {
        $file = $this->persist($upload, $source);
        $this->link($file, $linkable, $field);

        return $file->url_file;
    }
}
