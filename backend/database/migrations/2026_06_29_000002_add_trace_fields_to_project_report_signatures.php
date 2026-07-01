<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_report_signatures', function (Blueprint $table): void {
            $table->string('trace_id', 80)->nullable()->after('id')->index();
            $table->string('external_endpoint', 500)->nullable()->after('response_payload');
            $table->unsignedSmallInteger('external_http_status')->nullable()->after('external_endpoint');
            $table->json('external_request_payload')->nullable()->after('external_http_status');
            $table->json('external_response_payload')->nullable()->after('external_request_payload');
            $table->string('external_error_type', 80)->nullable()->after('external_response_payload');
            $table->string('external_phase', 80)->nullable()->after('external_error_type');
        });
    }

    public function down(): void
    {
        Schema::table('project_report_signatures', function (Blueprint $table): void {
            $table->dropIndex(['trace_id']);
            $table->dropColumn([
                'trace_id',
                'external_endpoint',
                'external_http_status',
                'external_request_payload',
                'external_response_payload',
                'external_error_type',
                'external_phase',
            ]);
        });
    }
};
