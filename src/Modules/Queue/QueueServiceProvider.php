<?php

declare(strict_types=1);

namespace App\Modules\Queue;

use App\Database\Database;
use App\Modules\Queue\Application\Services\QueueService;
use App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface;
use App\Modules\Queue\Infrastructure\Repositories\SqliteQueueRepository;
use App\Shared\Container\ContainerInterface;
use App\Shared\Providers\ServiceProvider;
use Override;
use Psr\Log\LoggerInterface;

final class QueueServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->container->set(
            QueueRepositoryInterface::class,
            function (): SqliteQueueRepository {
                /** @var Database $database */
                $database = $this->container->get(Database::class);

                return new SqliteQueueRepository($database);
            },
        );

        $this->container->set(
            QueueService::class,
            function (): QueueService {
                /** @var QueueRepositoryInterface $queueRepository */
                $queueRepository = $this->container->get(QueueRepositoryInterface::class);
                /** @var LoggerInterface $logger */
                $logger = $this->container->get(LoggerInterface::class);
                /** @var ContainerInterface $container */
                $container = $this->container;

                return new QueueService($queueRepository, $logger, $container);
            },
        );
    }
}
