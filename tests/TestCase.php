<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Database;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Database $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = new Database('sqlite::memory:');
    }

    protected function runMigrations(): void
    {
        $paths = [
            __DIR__ . '/../src/Database/Migrations',
            __DIR__ . '/../src/Modules/Auth/Database/Migrations',
            __DIR__ . '/../src/Modules/Queue/Database/Migrations',
        ];

        foreach ($paths as $migrationsPath) {
            if (!is_dir($migrationsPath)) {
                continue;
            }

            /** @var array<string> $files */
            $files = glob($migrationsPath . '/*.sql');
            sort($files);

            foreach ($files as $file) {
                $sql = file_get_contents($file);
                if ($sql !== false) {
                    $this->database->exec($sql);
                }
            }
        }
    }
}
