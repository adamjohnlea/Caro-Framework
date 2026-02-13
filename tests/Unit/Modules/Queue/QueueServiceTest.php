<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Queue;

use App\Modules\Queue\Application\Services\QueueService;
use App\Modules\Queue\Domain\JobInterface;
use App\Modules\Queue\Domain\Models\QueuedJob;
use App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface;
use App\Shared\Container\ContainerInterface;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class QueueServiceTest extends TestCase
{
    private MockObject $repository;
    private MockObject $logger;
    private MockObject $container;
    private QueueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(QueueRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->service = new QueueService($this->repository, $this->logger, $this->container);
    }

    public function test_dispatch_creates_queued_job(): void
    {
        $job = new TestJob();

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (QueuedJob $queuedJob): bool => $queuedJob->getQueue() === 'email'
                && $queuedJob->getStatus() === 'pending'
                && $queuedJob->getAttempts() === 0
                && $queuedJob->getMaxAttempts() === 5))
            ->willReturnCallback(static function (QueuedJob $job): QueuedJob {
                $job->setId(1);

                return $job;
            });

        $result = $this->service->dispatch($job);

        $this->assertSame(1, $result->getId());
        $this->assertSame('email', $result->getQueue());
    }

    public function test_process_next_returns_false_when_no_jobs(): void
    {
        $this->repository->method('claimNext')->willReturn(null);

        $result = $this->service->processNext('default');

        $this->assertFalse($result);
    }

    public function test_process_next_marks_job_failed_when_class_not_found(): void
    {
        $job = new QueuedJob(
            id: 1,
            queue: 'default',
            jobClass: 'NonExistent\\Job\\Class',
            payload: '{}',
            status: 'processing',
            attempts: 1,
            maxAttempts: 3,
            errorMessage: null,
            availableAt: new DateTimeImmutable(),
            createdAt: new DateTimeImmutable(),
            completedAt: null,
        );

        $this->repository->method('claimNext')->willReturn($job);
        $this->repository->expects($this->once())->method('update');

        $result = $this->service->processNext('default');

        $this->assertTrue($result);
        $this->assertSame('failed', $job->getStatus());
    }

    public function test_retry_failed_resets_failed_jobs(): void
    {
        $job1 = new QueuedJob(
            id: 1,
            queue: 'default',
            jobClass: 'SomeJob',
            payload: '{}',
            status: 'failed',
            attempts: 3,
            maxAttempts: 3,
            errorMessage: 'Error',
            availableAt: new DateTimeImmutable(),
            createdAt: new DateTimeImmutable(),
            completedAt: null,
        );
        $job2 = new QueuedJob(
            id: 2,
            queue: 'default',
            jobClass: 'AnotherJob',
            payload: '{}',
            status: 'failed',
            attempts: 3,
            maxAttempts: 3,
            errorMessage: 'Error',
            availableAt: new DateTimeImmutable(),
            createdAt: new DateTimeImmutable(),
            completedAt: null,
        );

        $this->repository->method('findFailed')->willReturn([$job1, $job2]);
        $this->repository->expects($this->exactly(2))->method('update');

        $count = $this->service->retryFailed();

        $this->assertSame(2, $count);
        $this->assertSame('pending', $job1->getStatus());
        $this->assertSame(0, $job1->getAttempts());
        $this->assertNull($job1->getErrorMessage());
    }

    public function test_count_pending_delegates_to_repository(): void
    {
        $this->repository->method('countPending')->willReturn(5);

        $this->assertSame(5, $this->service->countPending());
    }
}

final readonly class TestJob implements JobInterface
{
    #[Override]
    public function handle(ContainerInterface $container): void
    {
    }

    #[Override]
    public function getQueue(): string
    {
        return 'email';
    }

    #[Override]
    public function getMaxAttempts(): int
    {
        return 5;
    }
}
