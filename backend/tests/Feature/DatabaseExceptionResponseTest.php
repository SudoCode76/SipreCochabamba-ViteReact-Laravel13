<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class DatabaseExceptionResponseTest extends TestCase
{
    public function test_schema_query_errors_return_migration_hint(): void
    {
        foreach (['42P01', '42703'] as $sqlState) {
            $path = '/api/test-schema-query-error-'.$sqlState;

            Route::get($path, fn () => throw $this->queryException($sqlState));

            $this->getJson($path)
                ->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'La base de datos no está actualizada. Ejecute las migraciones pendientes.',
                    'errors' => [
                        'code' => 'DB_SCHEMA_MISMATCH',
                        'action' => 'run_migrations',
                    ],
                ]);
        }
    }

    public function test_other_query_errors_return_safe_database_message(): void
    {
        Route::get('/api/test-generic-query-error', fn () => throw $this->queryException('22001'));

        $this->getJson('/api/test-generic-query-error')
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'No se pudo consultar la base de datos. Revise los logs del servidor.',
                'errors' => [
                    'code' => 'DB_QUERY_ERROR',
                ],
            ]);
    }

    private function queryException(string $sqlState): QueryException
    {
        $previous = new PDOException('database error');
        $previous->errorInfo = [$sqlState];

        return new QueryException('pgsql', 'select * from missing_table', [], $previous);
    }
}
