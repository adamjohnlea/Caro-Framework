<?php

declare(strict_types=1);

namespace App\Database;

use Psr\Log\LoggerInterface;

final readonly class MigrationRunner
{
    public function __construct(
        private Database $database,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, bool> $enabledModules
     */
    public function run(array $enabledModules = []): void
    {
        $this->createMigrationsTable();

        $files = $this->collectMigrationFiles($enabledModules);
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);

            if ($this->hasRun($filename)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }

            $this->database->exec($sql);
            $this->recordMigration($filename);
            $this->logger->info('Migration applied: ' . $filename);
        }
    }

    /**
     * @param  array<string, bool> $enabledModules
     * @return list<string>
     */
    private function collectMigrationFiles(array $enabledModules): array
    {
        $files = [];

        $corePath = __DIR__ . '/Migrations';
        $coreFiles = glob($corePath . '/*.sql');
        if ($coreFiles !== false) {
            $files = array_merge($files, $coreFiles);
        }

        $moduleMap = [
            'auth' => 'Auth',
            'email' => 'Email',
            'queue' => 'Queue',
        ];

        foreach ($enabledModules as $module => $enabled) {
            if (!$enabled) {
                continue;
            }

            $moduleName = $moduleMap[$module] ?? ucfirst($module);
            $modulePath = __DIR__ . '/../Modules/' . $moduleName . '/Database/Migrations';

            if (!is_dir($modulePath)) {
                continue;
            }

            $moduleFiles = glob($modulePath . '/*.sql');
            if ($moduleFiles !== false) {
                $files = array_merge($files, $moduleFiles);
            }
        }

        return $files;
    }

    private function createMigrationsTable(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL UNIQUE,
                ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
    }

    private function hasRun(string $filename): bool
    {
        $stmt = $this->database->query(
            'SELECT COUNT(*) as count FROM migrations WHERE filename = ?',
            [$filename],
        );

        /** @var array{count: int} $row */
        $row = $stmt->fetch();

        return $row['count'] > 0;
    }

    private function recordMigration(string $filename): void
    {
        $this->database->query(
            'INSERT INTO migrations (filename) VALUES (?)',
            [$filename],
        );
    }
}
