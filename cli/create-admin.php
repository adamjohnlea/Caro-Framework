<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Database\DatabaseFactory;
use App\Database\MigrationRunner;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$options = getopt('', ['email:', 'password:', 'role::']);

if (!isset($options['email'], $options['password'])) {
    echo "Usage: php cli/create-admin.php --email=admin@example.com --password=secret123 [--role=admin]\n";
    echo "Roles: admin, viewer\n";
    exit(1);
}

/** @var string $email */
$email = $options['email'];
/** @var string $password */
$password = $options['password'];
/** @var string $role */
$role = $options['role'] ?? 'admin';

/** @var array{database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}} $config */
$database = DatabaseFactory::create($config['database']);

$logger = new Logger('cli');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));
$migrationRunner = new MigrationRunner($database, $logger);
$migrationRunner->run($config['modules']);

$userRepository = new SqliteUserRepository($database);
$userService = new UserService($userRepository);

try {
    $user = $userService->create($email, $password, $role);
    echo "User created successfully!\n";
    echo '  ID:    ' . $user->getId() . "\n";
    echo '  Email: ' . $user->getEmail()->getValue() . "\n";
    echo '  Role:  ' . $user->getRole()->label() . "\n";
} catch (\App\Shared\Exceptions\ValidationException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
