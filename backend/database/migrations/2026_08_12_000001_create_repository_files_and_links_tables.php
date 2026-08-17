<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repository_files')) {
            Schema::create('repository_files', function (Blueprint $table): void {
                $table->id();
                $table->string('repository_id')->unique();
                $table->text('url_file');
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('collector')->nullable();
                $table->string('system_id')->nullable();
                $table->json('response_payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('repository_file_links')) {
            Schema::create('repository_file_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repository_file_id')->constrained('repository_files')->cascadeOnDelete();
                $table->morphs('linkable');
                $table->string('field')->nullable();
                $table->timestamps();

                $table->unique(['repository_file_id', 'linkable_type', 'linkable_id', 'field'], 'repository_file_links_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repository_file_links')) {
            Schema::drop('repository_file_links');
        }

        if (Schema::hasTable('repository_files')) {
            Schema::drop('repository_files');
        }
    }
};
