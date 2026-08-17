<?php

namespace App\Console\Commands;

use App\Models\InputQuote;
use App\Models\InputRequest;
use App\Models\Item;
use App\Models\RepositoryFile;
use App\Models\RepositoryFileLink;
use App\Services\Repository\RepositoryClient;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MigrateLegacyRepositoryPdfs extends Command
{
    protected $signature = 'repository:migrate-legacy-pdfs
        {--legacy-root= : Absolute root of the legacy or Laravel public upload tree}
        {--dry-run : Inventory only; never writes the database or calls the repository API}
        {--resume : Skip references and physical files already recorded in the repository metadata}
        {--only= : Comma-separated groups: specs, sheets, quotes}
        {--include-unreferenced : Upload valid PDFs found in the selected document directories without a database reference}
        {--limit= : Maximum number of database references and unreferenced PDFs to process}
        {--output= : Output directory, or a JSON/CSV filename prefix}';

    protected $description = 'Inventories and safely migrates legacy PDFs to the repository.';

    /** @var array<int, array<string, mixed>> */
    private array $inventory = [];

    /** @var array<int, array<string, mixed>> */
    private array $physicalReport = [];

    /** @var array<string, int> */
    private array $summary = [];

    /** @var array<string, bool> */
    private array $referencedPaths = [];

    public function handle(RepositoryClient $repository): int
    {
        $root = $this->legacyRoot();
        $only = $this->onlyGroups();
        $limit = $this->option('limit') === null ? null : (int) $this->option('limit');

        if ($limit !== null && $limit < 1) {
            $this->error('--limit debe ser un entero mayor que cero.');

            return self::INVALID;
        }

        if (! is_dir($root)) {
            $this->error("No existe --legacy-root: {$root}");

            return self::FAILURE;
        }

        $lock = $this->acquireLock();
        if ($lock === null) {
            $this->error('Ya existe una migración de PDFs al repositorio en ejecución.');

            return self::FAILURE;
        }

        try {
            $this->inventory = [];
            $this->physicalReport = [];
            $this->referencedPaths = [];
            $this->summary = array_fill_keys([
                'referenced', 'unreferenced', 'migrated', 'reused', 'skipped_resume', 'missing', 'invalid_pdf', 'errors', 'dry_run',
            ], 0);

            foreach ($this->references($only) as $reference) {
                if ($this->reachedLimit($limit)) {
                    break;
                }

                $this->processReference($reference, $root, $repository);
            }

            $this->scanPhysicalFiles($root, $only, $repository, $limit);
            $paths = $this->writeReports($root);

            $this->table(['Referenced', 'Unreferenced', 'Migrated', 'Reused', 'Resume', 'Missing', 'Invalid', 'Errors'], [[
                $this->summary['referenced'], $this->summary['unreferenced'], $this->summary['migrated'], $this->summary['reused'],
                $this->summary['skipped_resume'], $this->summary['missing'], $this->summary['invalid_pdf'], $this->summary['errors'],
            ]]);
            $this->info('Reportes: '.implode(', ', $paths));

            return self::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<int, array{model: class-string, id: int|string, field: string, value: mixed, group: string}> */
    private function references(array $only): array
    {
        $definitions = [
            'specs' => [Item::class, ['especificacion']],
            'sheets' => [Item::class, ['ficha']],
            'quotes' => [InputRequest::class, ['archivo', 'archivo1', 'archivo2']],
            'quote_files' => [InputQuote::class, ['archivo', 'archivo1', 'archivo2', 'archivo3']],
        ];
        $result = [];

        foreach ($definitions as $group => [$model, $fields]) {
            $selection = $group === 'quote_files' ? 'quotes' : $group;
            if (! in_array($selection, $only, true) || ! Schema::hasTable((new $model)->getTable())) {
                continue;
            }

            $fields = array_values(array_filter(
                $fields,
                fn (string $field): bool => Schema::hasColumn((new $model)->getTable(), $field),
            ));
            if ($fields === []) {
                continue;
            }

            foreach ($model::query()->select(array_merge([(new $model)->getKeyName()], $fields))->cursor() as $record) {
                foreach ($fields as $field) {
                    if (filled($record->getAttribute($field))) {
                        $result[] = [
                            'model' => $model,
                            'id' => $record->getKey(),
                            'field' => $field,
                            'value' => $record->getAttribute($field),
                            'group' => $selection,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /** @param array{model: class-string, id: int|string, field: string, value: mixed, group: string} $reference */
    private function processReference(array $reference, string $root, RepositoryClient $repository): void
    {
        $this->summary['referenced']++;
        $row = $this->row($reference, 'reference');
        $model = $reference['model'];
        $record = $model::find($reference['id']);

        if ($record === null) {
            return;
        }

        if ($this->option('resume') && ($this->hasLink($record, $reference['field']) || $this->isRepositoryUrl($reference['value']))) {
            $this->finish($row, 'skipped_resume');

            return;
        }

        $path = $this->resolveLegacyPath((string) $reference['value'], $root);
        if ($path === null) {
            $this->finish($row, 'missing', 'No se encontró una ruta local segura.');

            return;
        }

        $this->referencedPaths[$path] = true;
        $row['resolved_path'] = $path;
        $this->processPdf($row, $path, $repository, function (RepositoryFile $file) use ($record, $reference): void {
            DB::transaction(fn () => $this->linkAndUpdate($file, $record, $reference['field']));
        });
    }

    private function processUnreferencedPdf(string $path, string $root, RepositoryClient $repository): void
    {
        $this->summary['unreferenced']++;
        $row = $this->row([
            'model' => null,
            'id' => null,
            'field' => null,
            'value' => null,
            'group' => 'physical',
        ], 'physical');
        $row['resolved_path'] = $path;
        $row['relative_path'] = $this->relativePath($path, $root);
        $this->processPdf($row, $path, $repository);
    }

    /** @param array<string, mixed> $row */
    private function processPdf(array $row, string $path, RepositoryClient $repository, ?callable $afterPersist = null): void
    {
        $header = file_get_contents($path, false, null, 0, 1024);
        $mime = mime_content_type($path) ?: '';

        if ($header === false || ! str_starts_with($header, '%PDF-') || $mime !== 'application/pdf') {
            $this->finish($row, 'invalid_pdf', "Cabecera o MIME inválido ({$mime}).");

            return;
        }

        $row['sha256'] = hash_file('sha256', $path);
        $row['size_bytes'] = filesize($path);
        if ($row['sha256'] === false || $row['size_bytes'] === false) {
            $this->finish($row, 'errors', 'No se pudo calcular la huella del archivo.');

            return;
        }

        if ($this->option('dry-run')) {
            $this->finish($row, 'dry_run');

            return;
        }

        try {
            $file = $this->fileByFingerprint($row['sha256'], $row['size_bytes']);

            if ($file !== null) {
                if ($afterPersist !== null) {
                    $afterPersist($file);
                }
                $row['repository_id'] = $file->repository_id;
                $row['url_file'] = $file->url_file;
                $row['detail'] = 'repository_file_id='.$file->id;
                $this->finish($row, $this->option('resume') ? 'skipped_resume' : 'reused');

                return;
            }

            $upload = $repository->upload(new UploadedFile($path, basename($path), 'application/pdf', null, true))[0];
            $file = RepositoryFile::query()->firstOrCreate(
                ['repository_id' => $upload->repositoryId],
                [
                    'url_file' => $upload->fileUrl,
                    'original_name' => basename($path),
                    'mime_type' => 'application/pdf',
                    'size_bytes' => $row['size_bytes'],
                    'collector' => config('services.repository.collector'),
                    'system_id' => config('services.repository.system_id'),
                    'response_payload' => array_merge($upload->payload, ['legacy_sha256' => $row['sha256']]),
                ],
            );

            if ($afterPersist !== null) {
                $afterPersist($file);
            }
            $row['repository_id'] = $file->repository_id;
            $row['url_file'] = $file->url_file;
            $row['detail'] = 'repository_file_id='.$file->id;
            $this->finish($row, 'migrated');
        } catch (Throwable $exception) {
            report($exception);
            $this->finish($row, 'errors', $exception->getMessage());
        }
    }

    private function linkAndUpdate(RepositoryFile $file, object $record, string $field): void
    {
        RepositoryFileLink::firstOrCreate([
            'repository_file_id' => $file->id,
            'linkable_type' => $record::class,
            'linkable_id' => $record->getKey(),
            'field' => $field,
        ]);
        $record->forceFill([$field => $file->url_file])->save();
    }

    private function hasLink(object $record, string $field): bool
    {
        return RepositoryFileLink::query()->where([
            'linkable_type' => $record::class,
            'linkable_id' => $record->getKey(),
            'field' => $field,
        ])->exists();
    }

    private function isRepositoryUrl(mixed $value): bool
    {
        $url = filter_var(trim((string) $value), FILTER_VALIDATE_URL);

        return $url !== false && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function fileByFingerprint(string $sha256, int $size): ?RepositoryFile
    {
        return RepositoryFile::query()->where('size_bytes', $size)->get()->first(
            fn (RepositoryFile $file): bool => in_array($sha256, [
                data_get($file->response_payload, 'legacy_sha256'),
                data_get($file->response_payload, 'sha256'),
            ], true),
        );
    }

    private function resolveLegacyPath(string $value, string $root): ?string
    {
        $value = rawurldecode(trim(str_replace('\\', '/', $value)));
        if ($value === '' || Str::contains($value, ['://', "\0"])) {
            return null;
        }

        $relative = ltrim(preg_replace('#^(?:\.?/)*(?:(?:public|storage)/)*(?:uploads?|files?)/#i', '', $value), '/');
        $storageRelative = ltrim(preg_replace('#^(?:\.?/)*(?:public|storage)/#i', '', $value), '/');
        $candidates = [$value, $relative, $storageRelative, 'uploads/'.$relative, 'upload/'.$relative, 'files/'.$relative];
        $rootReal = realpath($root);

        foreach (array_unique($candidates) as $candidate) {
            $path = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $candidate));
            if ($path !== false && $rootReal !== false && str_starts_with($path, $rootReal.DIRECTORY_SEPARATOR) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function reachedLimit(?int $limit): bool
    {
        return $limit !== null && ($this->summary['referenced'] + $this->summary['unreferenced']) >= $limit;
    }

    /** @param array<string, mixed> $reference
     *  @return array<string, mixed>
     */
    private function row(array $reference, string $source): array
    {
        return [
            'source' => $source,
            'group' => $reference['group'],
            'model' => $reference['model'],
            'id' => $reference['id'],
            'field' => $reference['field'],
            'value' => $reference['value'],
            'relative_path' => null,
            'status' => null,
            'resolved_path' => null,
            'sha256' => null,
            'size_bytes' => null,
            'repository_id' => null,
            'url_file' => null,
            'detail' => null,
        ];
    }

    /** @param array<string, mixed> $row */
    private function finish(array $row, string $status, ?string $detail = null): void
    {
        $row['status'] = $status;
        $row['detail'] = $detail ?? $row['detail'];
        $this->summary[$status]++;
        $this->inventory[] = $row;
    }

    /** @return array<int, string> */
    private function onlyGroups(): array
    {
        $allowed = ['specs', 'sheets', 'quotes'];
        $provided = $this->option('only');
        if ($provided === null || $provided === '') {
            return $allowed;
        }

        $only = array_values(array_unique(array_filter(array_map('trim', explode(',', $provided)))));
        if ($only === [] || array_diff($only, $allowed)) {
            throw new RuntimeException('--only acepta únicamente specs,sheets,quotes.');
        }

        return $only;
    }

    private function legacyRoot(): string
    {
        return rtrim((string) ($this->option('legacy-root') ?: base_path('../legacy')), DIRECTORY_SEPARATOR);
    }

    private function acquireLock()
    {
        $directory = storage_path('app/repository-migration');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio de bloqueo {$directory}.");
        }

        $lock = fopen($directory.DIRECTORY_SEPARATOR.'legacy-pdfs.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return null;
        }

        return $lock;
    }

    private function scanPhysicalFiles(string $root, array $only, RepositoryClient $repository, ?int $limit): void
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return;
        }

        foreach ($this->documentDirectories($rootReal, $only) as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = $file->getRealPath();
                if ($path === false || isset($this->referencedPaths[$path])) {
                    continue;
                }

                $isPdf = strtolower($file->getExtension()) === 'pdf';
                if (! $isPdf) {
                    $this->physicalReport[] = [
                        'path' => $path,
                        'classification' => 'non_pdf',
                        'action' => 'report_only',
                    ];

                    continue;
                }

                if (! $this->option('include-unreferenced')) {
                    $this->physicalReport[] = [
                        'path' => $path,
                        'classification' => 'orphan_pdf',
                        'action' => 'report_only',
                    ];

                    continue;
                }

                if ($this->reachedLimit($limit)) {
                    $this->physicalReport[] = [
                        'path' => $path,
                        'classification' => 'orphan_pdf',
                        'action' => 'limit_reached',
                    ];

                    continue;
                }

                $this->processUnreferencedPdf($path, $rootReal, $repository);
            }
        }
    }

    /** @return array<int, string> */
    private function documentDirectories(string $root, array $only): array
    {
        $directories = [];
        if (in_array('specs', $only, true)) {
            $directories = array_merge($directories, ['archivos/especificaciones', 'archivos/especificaciones_ant', 'especificaciones', 'especificaciones_ant']);
        }
        if (in_array('sheets', $only, true)) {
            $directories = array_merge($directories, ['archivos/fichas_tecnicas', 'archivos/fichas_tecnicas_ant', 'fichas_tecnicas', 'fichas_tecnicas_ant']);
        }
        if (in_array('quotes', $only, true)) {
            $directories = array_merge($directories, ['archivos/cotizaciones', 'archivos/cotizaciones_ant', 'cotizaciones_ant', 'precios_unitarios']);
            foreach (glob($root.'/*_ant/precios_unitarios', GLOB_ONLYDIR) ?: [] as $directory) {
                $directories[] = substr($directory, strlen($root) + 1);
            }
        }

        return array_values(array_unique(array_map(
            fn (string $directory): string => $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory),
            $directories,
        )));
    }

    private function relativePath(string $path, string $root): string
    {
        return str_replace('\\', '/', substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
    }

    /** @return array<int, string> */
    private function writeReports(string $root): array
    {
        $output = (string) ($this->option('output') ?: storage_path('app/legacy-pdf-migration'));
        $extension = pathinfo($output, PATHINFO_EXTENSION);
        $directory = $extension === '' ? $output : dirname($output);
        $prefix = $extension === '' ? 'legacy-pdf-migration' : pathinfo($output, PATHINFO_FILENAME);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio de salida {$directory}.");
        }

        $json = $directory.DIRECTORY_SEPARATOR.$prefix.'-summary.json';
        $inventoryCsv = $directory.DIRECTORY_SEPARATOR.$prefix.'-inventory.csv';
        $physicalCsv = $directory.DIRECTORY_SEPARATOR.$prefix.'-physical-report.csv';
        file_put_contents($json, json_encode([
            'generated_at' => now()->toIso8601String(), 'legacy_root' => $root, 'dry_run' => (bool) $this->option('dry-run'),
            'include_unreferenced' => (bool) $this->option('include-unreferenced'), 'summary' => $this->summary,
            'inventory' => $this->inventory, 'physical_report' => $this->physicalReport,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->writeCsv($inventoryCsv, $this->inventory);
        $this->writeCsv($physicalCsv, $this->physicalReport);

        return [$json, $inventoryCsv, $physicalCsv];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        $headers = $rows === [] ? [] : array_keys($rows[0]);
        if ($headers !== []) {
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row));
            }
        }
        fclose($handle);
    }
}
