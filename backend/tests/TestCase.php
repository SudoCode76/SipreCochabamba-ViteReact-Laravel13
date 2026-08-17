<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    private int $repositoryUploadSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            (string) config('services.repository.endpoint') => function ($request) {
                $count = count(array_filter(
                    $request->data(),
                    static fn ($part): bool => is_array($part) && ($part['name'] ?? null) === 'file[]',
                ));

                $count = max(1, $count);
                $first = $this->repositoryUploadSequence + 1;
                $this->repositoryUploadSequence += $count;

                return Http::response([
                    'status' => true,
                    'response' => collect(range($first, $this->repositoryUploadSequence))->map(fn (int $index): array => [
                        'id_repository' => 'test-repository-file-'.$index,
                        'url_file' => 'https://repository.test/files/document-'.$index.'.pdf',
                    ])->all(),
                ], 201);
            },
        ]);
    }
}
