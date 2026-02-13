<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\DatabaseFactory;
use App\Database\MigrationRunner;
use App\Modules\Queue\Application\Services\QueueService;
use App\Modules\Queue\Infrastructure\Repositories\SqliteQueueRepository;
use App\Modules\Queue\Infrastructure\Worker;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/** @var array{database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}} $config */
if (!$config['modules']['queue']) {
    echo "Queue module is not enabled. Set MODULE_QUEUE=true in .env\n";
    exit(1);
}

$options = getopt('', ['queue::', 'sleep::']);
/** @var string $queue */
$queue = $options['queue'] ?? 'default';
/** @var string $sleepStr */
$sleepStr = $options['sleep'] ?? '3';
$sleep = (int) $sleepStr;

$logger = new Logger('worker');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
$logger->pushHandler(new StreamHandler(__DIR__ . '/../storage/logs/worker.log', Logger::WARNING));

$database = DatabaseFactory::create($config['database']);
$migrationRunner = new MigrationRunner($database, $logger);
$migrationRunner->run($config['modules']);

$queueRepository = new SqliteQueueRepository($database);
$queueService = new QueueService($queueRepository, $logger);

$worker = new Worker($queueService, $logger);

echo "Starting worker on queue '{$queue}' (sleep: {$sleep}s)...\n";
$worker->run($queue, $sleep);
