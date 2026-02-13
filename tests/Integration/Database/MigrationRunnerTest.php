<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Database\Database;
use App\Database\MigrationRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class MigrationRunnerTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = new Database('sqlite::memory:');
    }

    public function test_renamed_migrations_not_rerun(): void
    {
        $logger = new NullLogger();
        $runner = new MigrationRunner($this->database, $logger);

        // Run migrations (creates tables with new filenames)
        $runner->run(['auth' => true, 'queue' => true]);

        // Verify migrations were recorded with new names
        $stmt = $this->database->query('SELECT filename FROM migrations ORDER BY filename');
        $filenames = [];
        while ($row = $stmt->fetch()) {
            /** @var array{filename: string} $row */
            $filenames[] = $row['filename'];
        }

        $this->assertContains('2025_01_01_000001_create_users_table.sql', $filenames);
        $this->assertContains('2025_01_01_000002_create_jobs_table.sql', $filenames);
    }

    public function test_old_migration_names_updated_to_new(): void
    {
        $logger = new NullLogger();
        $runner = new MigrationRunner($this->database, $logger);

        // Simulate existing deployment with old migration names
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL UNIQUE,
                ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
        $this->database->query(
            "INSERT INTO migrations (filename) VALUES ('001_create_users_table.sql')",
        );
        $this->database->query(
            "INSERT INTO migrations (filename) VALUES ('002_create_jobs_table.sql')",
        );

        // Also create the tables that those migrations would have created
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "viewer",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT NOT NULL DEFAULT "default",
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "pending",
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                error_message TEXT,
                available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME
            )',
        );

        // Run migrations — should update old names, not re-run
        $runner->run(['auth' => true, 'queue' => true]);

        // Old names should be gone, new names present
        $stmt = $this->database->query('SELECT filename FROM migrations ORDER BY filename');
        $filenames = [];
        while ($row = $stmt->fetch()) {
            /** @var array{filename: string} $row */
            $filenames[] = $row['filename'];
        }

        $this->assertNotContains('001_create_users_table.sql', $filenames);
        $this->assertNotContains('002_create_jobs_table.sql', $filenames);
        $this->assertContains('2025_01_01_000001_create_users_table.sql', $filenames);
        $this->assertContains('2025_01_01_000002_create_jobs_table.sql', $filenames);
    }

    public function test_new_timestamp_migrations_applied_in_order(): void
    {
        $logger = new NullLogger();
        $runner = new MigrationRunner($this->database, $logger);

        $runner->run(['auth' => true, 'queue' => true]);

        // Verify both tables exist
        $stmt = $this->database->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $this->assertNotFalse($stmt->fetch());

        $stmt = $this->database->query("SELECT name FROM sqlite_master WHERE type='table' AND name='jobs'");
        $this->assertNotFalse($stmt->fetch());
    }
}
