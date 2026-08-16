<?php

namespace App\Services;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

final class SystemBackupService
{
    private const FORMAT_VERSION = 1;

    /** Create a ZIP backup without changing the database. */
    public function export(): string
    {
        return $this->withOperationLock(function (): string {
            $directory = storage_path('app/private/backups');
            File::ensureDirectoryExists($directory);

            $archivePath = $this->temporaryPath($directory, 'nossa-casa-backup-');
            $databasePath = null;
            $temporaryFiles = [];
            $zip = new ZipArchive;

            try {
                if ($zip->open($archivePath, ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException(__('backup.create_archive_failed'));
                }

                $databasePath = $this->temporaryPath($directory, 'nossa-casa-database-');
                $database = $this->writeDatabaseDump($databasePath);
                $zip->addFile($databasePath, 'database.sql');

                $media = ['disks' => [], 'files' => 0, 'bytes' => 0];

                foreach ($this->mediaDisks() as $diskName) {
                    $disk = Storage::disk($diskName);
                    $diskFiles = 0;
                    $diskBytes = 0;

                    foreach ($disk->allFiles('') as $path) {
                        $stream = $disk->readStream($path);

                        if (! is_resource($stream)) {
                            throw new RuntimeException(__('backup.read_media_failed', ['path' => $diskName.'/'.$path]));
                        }

                        $temporaryFile = $this->temporaryPath($directory, 'nossa-casa-media-');
                        $temporaryFiles[] = $temporaryFile;
                        $target = fopen($temporaryFile, 'wb');

                        if (! is_resource($target)) {
                            fclose($stream);
                            throw new RuntimeException(__('backup.prepare_media_failed'));
                        }

                        try {
                            stream_copy_to_stream($stream, $target);
                        } finally {
                            fclose($stream);
                            fclose($target);
                        }

                        $zipPath = 'media/'.$diskName.'/'.$path;

                        if (! $zip->addFile($temporaryFile, $zipPath)) {
                            throw new RuntimeException(__('backup.add_media_failed', ['path' => $zipPath]));
                        }

                        $diskFiles++;
                        $diskBytes += File::size($temporaryFile);
                    }

                    $media['disks'][$diskName] = ['files' => $diskFiles, 'bytes' => $diskBytes];
                    $media['files'] += $diskFiles;
                    $media['bytes'] += $diskBytes;
                }

                $manifest = [
                    'format_version' => self::FORMAT_VERSION,
                    'created_at' => now()->toIso8601String(),
                    'database' => $database,
                    'media' => $media,
                ];

                $zip->addFromString(
                    'manifest.json',
                    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                );
                $zip->close();

                return $archivePath;
            } catch (Throwable $exception) {
                $zip->close();
                File::delete($archivePath);

                throw $exception;
            } finally {
                if ($databasePath) {
                    File::delete($databasePath);
                }

                File::delete($temporaryFiles);
            }
        });
    }

    /** Restore database rows and media from a previously exported ZIP. */
    public function import(UploadedFile $archive): void
    {
        $this->withOperationLock(function () use ($archive): void {
            $directory = storage_path('app/private/backups');
            File::ensureDirectoryExists($directory);
            $stagingDirectory = $this->temporaryDirectory($directory, 'nossa-casa-import-');
            $zip = new ZipArchive;
            $createdMedia = [];

            try {
                if ($zip->open($archive->getRealPath()) !== true) {
                    throw new RuntimeException(__('backup.invalid_zip'));
                }

                $manifestContents = $zip->getFromName('manifest.json');
                $manifest = is_string($manifestContents)
                    ? json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR)
                    : null;

                $this->validateManifest($manifest);

                $databasePath = $this->stageDatabase($zip, $stagingDirectory);
                $mediaFiles = $this->stageMedia($zip, $stagingDirectory, $manifest['media']['disks']);
                $this->validateMediaConflicts($mediaFiles);
                $createdMedia = $this->writeMissingMedia($mediaFiles);

                try {
                    $this->restoreDatabase($databasePath, $manifest['database']['tables']);
                } catch (Throwable $exception) {
                    $this->deleteCreatedMedia($createdMedia);

                    throw $exception;
                }
            } finally {
                $zip->close();
                File::deleteDirectory($stagingDirectory);
            }
        });
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $operation
     * @return TReturn
     */
    private function withOperationLock(Closure $operation): mixed
    {
        $lockPath = storage_path('framework/backup-operation.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        $handle = fopen($lockPath, 'c');

        if (! is_resource($handle)) {
            throw new RuntimeException(__('backup.lock_failed'));
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw new RuntimeException(__('backup.operation_in_progress'));
        }

        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array{driver: string, tables: list<string>} */
    private function writeDatabaseDump(string $path): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();
        $tables = $this->databaseTables($pdo, $driver);
        $handle = fopen($path, 'wb');

        if (! is_resource($handle)) {
            throw new RuntimeException(__('backup.database_dump_failed'));
        }

        try {
            foreach ($tables as $table) {
                $schema = $this->tableSchema($pdo, $driver, $table);

                if ($schema) {
                    $schema = rtrim($schema, " \t\r\n;");
                    fwrite($handle, $schema.";\n\n");
                }

                $query = $pdo->query('SELECT * FROM '.$this->quoteIdentifier($table, $driver));

                if (! $query) {
                    continue;
                }

                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $columns = array_keys($row);
                    $values = array_map(
                        fn (mixed $value): string => $value === null ? 'NULL' : $this->quoteValue($pdo, $value),
                        array_values($row),
                    );

                    fwrite(
                        $handle,
                        'INSERT INTO '.$this->quoteIdentifier($table, $driver)
                            .' ('.implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column, $driver), $columns)).')'
                            .' VALUES ('.implode(', ', $values).');'."\n",
                    );
                }
            }
        } finally {
            fclose($handle);
        }

        return ['driver' => $driver, 'tables' => $tables];
    }

    /** @return list<string> */
    private function databaseTables(PDO $pdo, string $driver): array
    {
        $query = match ($driver) {
            'mysql', 'mariadb' => $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"),
            'sqlite' => $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
            default => throw new RuntimeException(__('backup.unsupported_driver', ['driver' => $driver])),
        };

        return array_values(array_map(
            fn (array $row): string => (string) array_values($row)[0],
            $query?->fetchAll(PDO::FETCH_ASSOC) ?? [],
        ));
    }

    private function tableSchema(PDO $pdo, string $driver, string $table): ?string
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query = $pdo->query('SHOW CREATE TABLE '.$this->quoteIdentifier($table, $driver));
            $row = $query?->fetch(PDO::FETCH_NUM);

            return $row ? (string) ($row[1] ?? '') : null;
        }

        $query = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
        $query->execute([$table]);

        return ($schema = $query->fetchColumn()) ? (string) $schema : null;
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => '`'.str_replace('`', '``', $identifier).'`',
            'sqlite' => '"'.str_replace('"', '""', $identifier).'"',
            default => throw new RuntimeException(__('backup.unsupported_driver', ['driver' => $driver])),
        };
    }

    private function quoteValue(PDO $pdo, mixed $value): string
    {
        $quoted = $pdo->quote((string) $value);

        if ($quoted === false) {
            throw new RuntimeException(__('backup.quote_value_failed'));
        }

        return $quoted;
    }

    private function validateManifest(mixed $manifest): void
    {
        if (! is_array($manifest)
            || ($manifest['format_version'] ?? null) !== self::FORMAT_VERSION
            || ! is_array($manifest['database'] ?? null)
            || ! is_array($manifest['database']['tables'] ?? null)
            || ! is_array($manifest['media']['disks'] ?? null)) {
            throw new RuntimeException(__('backup.invalid_format'));
        }

        if (! array_is_list($manifest['database']['tables'])
            || ! collect($manifest['database']['tables'])->every(fn (mixed $table): bool => is_string($table) && $table !== '')
            || ($manifest['database']['driver'] ?? null) !== DB::connection()->getDriverName()) {
            throw new RuntimeException(__('backup.incompatible_database'));
        }

        foreach (array_keys($manifest['media']['disks']) as $diskName) {
            if (! in_array($diskName, $this->mediaDisks(), true)) {
                throw new RuntimeException(__('backup.disk_not_configured', ['disk' => $diskName]));
            }
        }
    }

    private function stageDatabase(ZipArchive $zip, string $directory): string
    {
        $stream = $zip->getStream('database.sql');

        if (! is_resource($stream)) {
            throw new RuntimeException(__('backup.missing_database_dump'));
        }

        $path = $directory.'/database.sql';
        $target = fopen($path, 'wb');

        if (! is_resource($target)) {
            fclose($stream);
            throw new RuntimeException(__('backup.prepare_database_dump_failed'));
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($stream);
            fclose($target);
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $disks
     * @return list<array{disk: string, path: string, staged: string}>
     */
    private function stageMedia(ZipArchive $zip, string $directory, array $disks): array
    {
        $files = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name) || ! str_starts_with($name, 'media/')) {
                continue;
            }

            $parts = explode('/', $name, 3);
            $diskName = $parts[1] ?? '';
            $path = $parts[2] ?? '';

            if ($diskName === '' || $path === '' || ! isset($disks[$diskName]) || $this->isUnsafeArchivePath($path)) {
                throw new RuntimeException(__('backup.invalid_media_path'));
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            $stream = $zip->getStream($name);
            $staged = $directory.'/media/'.$diskName.'/'.$path;
            File::ensureDirectoryExists(dirname($staged));
            $target = fopen($staged, 'wb');

            if (! is_resource($stream) || ! is_resource($target)) {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (is_resource($target)) {
                    fclose($target);
                }

                throw new RuntimeException(__('backup.extract_media_failed'));
            }

            try {
                stream_copy_to_stream($stream, $target);
            } finally {
                fclose($stream);
                fclose($target);
            }

            $files[] = ['disk' => $diskName, 'path' => $path, 'staged' => $staged];
        }

        return $files;
    }

    /** @param list<array{disk: string, path: string, staged: string}> $files */
    private function validateMediaConflicts(array $files): void
    {
        foreach ($files as $file) {
            $disk = Storage::disk($file['disk']);

            if (! $disk->exists($file['path'])) {
                continue;
            }

            if ($disk->size($file['path']) !== File::size($file['staged'])
                || $this->hashStorageFile($disk, $file['path']) !== hash_file('sha256', $file['staged'])) {
                throw new RuntimeException(__('backup.media_conflict', ['path' => $file['disk'].'/'.$file['path']]));
            }
        }
    }

    /** @param list<array{disk: string, path: string, staged: string}> $files */
    private function writeMissingMedia(array $files): array
    {
        $created = [];

        try {
            foreach ($files as $file) {
                $disk = Storage::disk($file['disk']);

                if ($disk->exists($file['path'])) {
                    continue;
                }

                $stream = fopen($file['staged'], 'rb');

                if (! is_resource($stream) || ! $disk->writeStream($file['path'], $stream)) {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    throw new RuntimeException(__('backup.restore_media_failed', ['path' => $file['disk'].'/'.$file['path']]));
                }

                fclose($stream);
                $created[] = ['disk' => $file['disk'], 'path' => $file['path']];
            }
        } catch (Throwable $exception) {
            $this->deleteCreatedMedia($created);

            throw $exception;
        }

        return $created;
    }

    /** @param list<array{disk: string, path: string}> $files */
    private function deleteCreatedMedia(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }

    /** @param list<string> $tables */
    private function restoreDatabase(string $path, array $tables): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();
        $statements = $this->sqlStatements($path);
        $schemaStatements = [];
        $dataStatements = [];

        foreach ($statements as $statement) {
            if (preg_match('/^CREATE\s+TABLE\b/i', $statement)) {
                $schemaStatements[] = $statement;
            } else {
                $dataStatements[] = $statement;
            }
        }

        foreach ($schemaStatements as $statement) {
            $createStatement = preg_replace('/^CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $statement, 1);

            if (! is_string($createStatement)) {
                throw new RuntimeException(__('backup.prepare_schema_failed'));
            }

            $pdo->exec($createStatement);
        }

        $this->disableForeignKeys($pdo, $driver);
        $ownsTransaction = ! $pdo->inTransaction();
        $savepoint = 'system_backup_restore';

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT '.$savepoint);
        }

        try {
            foreach ($tables as $table) {
                $pdo->exec('DELETE FROM '.$this->quoteIdentifier((string) $table, $driver));
            }

            foreach ($dataStatements as $statement) {
                if (preg_match('/^(SET\s+FOREIGN_KEY_CHECKS|PRAGMA\s+FOREIGN_KEYS)/i', $statement)) {
                    continue;
                }

                $pdo->exec($statement);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            } elseif (! $ownsTransaction) {
                $pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
                $pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            }

            throw $exception;
        } finally {
            $this->enableForeignKeys($pdo, $driver);
        }
    }

    /** @return list<string> */
    private function sqlStatements(string $path): array
    {
        $handle = fopen($path, 'rb');

        if (! is_resource($handle)) {
            throw new RuntimeException(__('backup.read_database_dump_failed'));
        }

        $statements = [];
        $statement = '';
        $quote = null;
        $pending = null;

        try {
            while (($character = $pending ?? fgetc($handle)) !== false) {
                $pending = null;

                if ($quote !== null) {
                    $statement .= $character;

                    if ($character === '\\') {
                        $escaped = fgetc($handle);

                        if ($escaped !== false) {
                            $statement .= $escaped;
                        }

                        continue;
                    }

                    if ($character === $quote) {
                        $next = fgetc($handle);

                        if ($next === $quote) {
                            $statement .= $next;
                        } else {
                            $quote = null;
                            $pending = $next;
                        }
                    }

                    continue;
                }

                if (in_array($character, ["'", '"', '`'], true)) {
                    $quote = $character;
                    $statement .= $character;

                    continue;
                }

                if ($character === ';') {
                    if (trim($statement) !== '') {
                        $statements[] = trim($statement);
                    }

                    $statement = '';

                    continue;
                }

                $statement .= $character;
            }

            if (trim($statement) !== '') {
                $statements[] = trim($statement);
            }
        } finally {
            fclose($handle);
        }

        return $statements;
    }

    private function disableForeignKeys(PDO $pdo, string $driver): void
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }
    }

    private function enableForeignKeys(PDO $pdo, string $driver): void
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    /** @return list<string> */
    private function mediaDisks(): array
    {
        return array_values(array_unique(array_filter([
            (string) config('media.disk'),
            (string) config('media.archive_disk'),
        ])));
    }

    private function hashStorageFile(mixed $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException(__('backup.verify_media_failed', ['path' => $path]));
        }

        $context = hash_init('sha256');

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk !== false && $chunk !== '') {
                    hash_update($context, $chunk);
                }
            }
        } finally {
            fclose($stream);
        }

        return hash_final($context);
    }

    private function isUnsafeArchivePath(string $path): bool
    {
        return str_contains($path, '\\')
            || str_starts_with($path, '/')
            || in_array('..', explode('/', $path), true);
    }

    private function temporaryPath(string $directory, string $prefix): string
    {
        $path = tempnam($directory, $prefix);

        if ($path === false) {
            throw new RuntimeException(__('backup.temporary_file_failed'));
        }

        return $path;
    }

    private function temporaryDirectory(string $directory, string $prefix): string
    {
        $path = $this->temporaryPath($directory, $prefix);
        File::delete($path);
        File::ensureDirectoryExists($path);

        return $path;
    }
}
