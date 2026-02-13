<?php

declare(strict_types=1);

namespace App\Modules\Queue\Infrastructure;

use App\Modules\Queue\Application\Services\QueueService;
use Psr\Log\LoggerInterface;

final readonly class Worker
{
    public function __construct(
        private QueueService $queueService,
        private LoggerInterface $logger,
    ) {
    }

    public function run(string $queue = 'default', int $sleep = 3): void
    {
        $this->logger->info('Worker started', ['queue' => $queue]);

        $running = true;

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, static function () use (&$running): void {
                $running = false;
            });
            pcntl_signal(SIGINT, static function () use (&$running): void {
                $running = false;
            });
        }

        while ($running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $processed = $this->queueService->processNext($queue);

            if (!$processed) {
                sleep($sleep);
            }
        }

        $this->logger->info('Worker stopped gracefully');
    }
}
