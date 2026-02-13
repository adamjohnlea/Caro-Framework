<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

use App\Modules\Queue\Application\Services\QueueService;
use App\Modules\Queue\Infrastructure\Worker;
use App\Shared\Cli\CliBootstrap;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/** @var array{app: array{env: string, debug: bool, name: string}, database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}, ses: array{region: string, access_key: string, secret_key: string, from_address: string}} $config */
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

$container = CliBootstrap::createContainer($config, [
    new StreamHandler('php://stdout', Logger::INFO),
    new StreamHandler(__DIR__ . '/../storage/logs/worker.log', Logger::WARNING),
]);

/** @var QueueService $queueService */
$queueService = $container->get(QueueService::class);
/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);

$worker = new Worker($queueService, $logger);

echo "Starting worker on queue '{$queue}' (sleep: {$sleep}s)...\n";
$worker->run($queue, $sleep);
