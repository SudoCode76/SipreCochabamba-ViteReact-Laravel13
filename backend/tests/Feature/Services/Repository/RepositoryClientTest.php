<?php

namespace Tests\Feature\Services\Repository;

use App\Services\Repository\RepositoryClient;
use App\Services\Repository\RepositoryClientException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepositoryClientTest extends TestCase
{
    public function test_it_uploads_one_file_using_the_file_array_multipart_contract(): void
    {
        config()->set('services.repository.endpoint', 'https://repository.test/upload');
        config()->set('services.repository.connect_timeout', 3);
        config()->set('services.repository.timeout', 17);

        $body = null;

        Http::fake(function ($request) use (&$body) {
            $body = $request->body();

            return Http::response([
                'status' => true,
                'response' => [[
                    'id_repository' => 'repo-1',
                    'url_file' => 'https://repository.test/files/one.pdf',
                    'name_file' => 'one.pdf',
                ]],
            ], 201);
        });

        $result = app(RepositoryClient::class)->upload(
            UploadedFile::fake()->create('one.pdf', 12, 'application/pdf'),
            ['entity_id' => '42'],
        );

        $this->assertCount(1, $result);
        $this->assertSame('repo-1', $result[0]->repositoryId);
        $this->assertSame('https://repository.test/files/one.pdf', $result[0]->fileUrl);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://repository.test/upload'
            && $request->hasFile('file[]', null, 'one.pdf'));
        $this->assertIsString($body);
        $this->assertStringContainsString('name="sistema_id"', $body);
        $this->assertStringContainsString((string) config('services.repository.system_id'), $body);
        $this->assertStringContainsString('name="collector"', $body);
        $this->assertStringContainsString('name="entity_id"', $body);
        $this->assertStringContainsString('42', $body);
    }

    public function test_it_uploads_multiple_files_and_maps_the_response_entries(): void
    {
        config()->set('services.repository.endpoint', 'https://repository.test/upload');

        Http::fake([
            'https://repository.test/upload' => Http::response([
                'status' => true,
                'response' => [
                    ['id_repository' => 'repo-1', 'url_file' => 'https://repository.test/files/one.pdf'],
                    ['id_repository' => 'repo-2', 'url_file' => 'https://repository.test/files/two.pdf'],
                ],
            ], 201),
        ]);

        $result = app(RepositoryClient::class)->upload([
            UploadedFile::fake()->create('one.pdf', 12, 'application/pdf'),
            UploadedFile::fake()->create('two.pdf', 20, 'application/pdf'),
        ]);

        $this->assertSame(['repo-1', 'repo-2'], array_map(
            static fn ($item): string => $item->repositoryId,
            $result,
        ));

        Http::assertSent(function ($request): bool {
            return count(array_filter($request->data(), static fn ($part): bool => is_array($part) && ($part['name'] ?? null) === 'file[]')) === 2;
        });
    }

    public function test_it_rejects_a_success_payload_when_status_is_not_exactly_201(): void
    {
        config()->set('services.repository.endpoint', 'https://repository.test/upload');
        Http::fake(['https://repository.test/upload' => Http::response([
            'status' => true,
            'response' => [[
                'id_repository' => 'repo-1',
                'url_file' => 'https://repository.test/files/one.pdf',
            ]],
        ], 200)]);

        $this->expectException(RepositoryClientException::class);
        $this->expectExceptionMessage('rechazó el archivo');

        app(RepositoryClient::class)->upload(UploadedFile::fake()->create('one.pdf'));
    }

    public function test_it_rejects_a_response_that_does_not_match_the_repository_contract(): void
    {
        config()->set('services.repository.endpoint', 'https://repository.test/upload');
        Http::fake(['https://repository.test/upload' => Http::response([
            'status' => true,
            'response' => [['id_repository' => 'repo-1']],
        ], 201)]);

        $this->expectException(RepositoryClientException::class);
        $this->expectExceptionMessage('respuesta inválida');

        app(RepositoryClient::class)->upload(UploadedFile::fake()->create('one.pdf'));
    }
}
