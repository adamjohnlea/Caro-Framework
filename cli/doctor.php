<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\DatabaseFactory;
use App\Database\MigrationRunner;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

echo "Caro Framework Health Check\n";
echo "===========================\n";

/** @var array{app: array{name: string}, database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}} $config */
$logger = new Logger('doctor');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::ERROR));

$exitCode = 0;

// Check database
try {
    $database = DatabaseFactory::create($config['database']);
    $database->query('SELECT 1');
    $driver = ucfirst($config['database']['driver']);
    echo "[OK] Database: Connected ({$driver})\n";
} catch (\Throwable $e) {
    echo "[FAIL] Database: " . $e->getMessage() . "\n";
    $exitCode = 1;
}

// Check migrations
if (isset($database)) {
    try {
        $migrationRunner = new MigrationRunner($database, $logger);
        $migrationRunner->run($config['modules']);

        $stmt = $database->query('SELECT COUNT(*) as count FROM migrations');
        /** @var array{count: string|int} $row */
        $row = $stmt->fetch();
        echo "[OK] Migrations: " . (int) $row['count'] . " applied\n";
    } catch (\Throwable $e) {
        echo "[FAIL] Migrations: " . $e->getMessage() . "\n";
        $exitCode = 1;
    }
}

// Check auth module
if ($config['modules']['auth']) {
    if (isset($database)) {
        try {
            $stmt = $database->query('SELECT COUNT(*) as count FROM users');
            /** @var array{count: string|int} $row */
            $row = $stmt->fetch();
            echo "[OK] Auth: Enabled, " . (int) $row['count'] . " user(s)\n";
        } catch (\Throwable $e) {
            echo "[FAIL] Auth: " . $e->getMessage() . "\n";
            $exitCode = 1;
        }
    }
} else {
    echo "[--] Auth: Disabled\n";
}

// Check email module
if ($config['modules']['email']) {
    /** @var array{ses: array{access_key: string, secret_key: string}} $config */
    if ($config['ses']['access_key'] !== '' && $config['ses']['secret_key'] !== '') {
        echo "[OK] Email: Enabled (SES)\n";
    } else {
        echo "[OK] Email: Enabled (Log)\n";
    }
} else {
    echo "[--] Email: Disabled\n";
}

// Check queue module
if ($config['modules']['queue']) {
    if (isset($database)) {
        try {
            $stmt = $database->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'pending'");
            /** @var array{count: string|int} $row */
            $row = $stmt->fetch();
            echo "[OK] Queue: Enabled, " . (int) $row['count'] . " pending job(s)\n";
        } catch (\Throwable $e) {
            echo "[FAIL] Queue: " . $e->getMessage() . "\n";
            $exitCode = 1;
        }
    }
} else {
    echo "[--] Queue: Disabled\n";
}

exit($exitCode);
