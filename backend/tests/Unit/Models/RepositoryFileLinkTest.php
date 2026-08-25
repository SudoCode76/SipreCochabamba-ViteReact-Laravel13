<?php

namespace Tests\Unit\Models;

use App\Models\RepositoryFile;
use App\Models\RepositoryFileLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepositoryFileLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('repository_test_linkables', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('repository_files', function (Blueprint $table): void {
            $table->id();
            $table->string('repository_id')->unique();
            $table->text('url_file');
            $table->timestamps();
        });

        Schema::create('repository_file_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_file_id')->constrained('repository_files')->cascadeOnDelete();
            $table->morphs('linkable');
            $table->string('field')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_resolves_the_linked_model_through_a_polymorphic_relation(): void
    {
        $linkable = RepositoryTestLinkable::query()->create(['name' => 'Item de prueba']);
        $file = RepositoryFile::query()->create([
            'repository_id' => 'repo-1',
            'url_file' => 'https://repository.test/files/one.pdf',
        ]);
        $link = RepositoryFileLink::query()->create([
            'repository_file_id' => $file->id,
            'linkable_type' => $linkable->getMorphClass(),
            'linkable_id' => $linkable->getKey(),
            'field' => 'specification',
        ]);

        $this->assertInstanceOf(RepositoryTestLinkable::class, $link->linkable);
        $this->assertTrue($link->linkable->is($linkable));
        $this->assertTrue($link->repositoryFile->is($file));
        $this->assertTrue($file->links->first()->is($link));
    }
}

class RepositoryTestLinkable extends Model
{
    protected $table = 'repository_test_linkables';

    public $timestamps = false;

    protected $guarded = [];
}
