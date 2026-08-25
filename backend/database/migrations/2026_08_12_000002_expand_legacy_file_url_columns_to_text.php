<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'item' => ['especificacion', 'ficha'],
        'insumo' => ['archivo', 'archivo1', 'archivo2', 'archivo3'],
        'cotizaciones' => ['archivo', 'archivo1', 'archivo2', 'archivo3'],
        'solicitud_insumo' => ['archivo', 'archivo1', 'archivo2'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $this->changeToText($table, $column);
                }
            }
        }
    }

    public function down(): void
    {
        // Existing URLs may exceed varchar limits; this migration is intentionally irreversible.
    }

    private function changeToText(string $table, string $column): void
    {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $tableName = $grammar->wrapTable($table);
        $columnName = $grammar->wrap($column);
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN {$columnName} TYPE TEXT");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE {$tableName} MODIFY {$columnName} TEXT NULL");
        }
    }
};
