<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Modules\Queue\Domain\Models\QueuedJob;
use App\Modules\Queue\Infrastructure\Repositories\SqliteQueueRepository;
use DateTimeImmutable;
use Tests\TestCase;

final class SqliteQueueRepositoryTest extends TestCase
{
    private SqliteQueueRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->repository = new SqliteQueueRepository($this->database);
    }

    public function test_save_persists_job_and_assigns_id(): void
    {
        $job = $this->createJob();

        $saved = $this->repository->save($job);

        $this->assertNotNull($saved->getId());
        $this->assertSame('default', $saved->getQueue());
        $this->assertSame('pending', $saved->getStatus());
    }

    public function test_claim_next_returns_pending_job(): void
    {
        $this->repository->save($this->createJob());

        $claimed = $this->repository->claimNext('default');

        $this->assertNotNull($claimed);
        $this->assertSame('processing', $claimed->getStatus());
        $this->assertSame(1, $claimed->getAttempts());
    }

    public function test_claim_next_returns_null_when_no_pending_jobs(): void
    {
        $claimed = $this->repository->claimNext('default');

        $this->assertNull($claimed);
    }

    public function test_claim_next_respects_queue_name(): void
    {
        $this->repository->save($this->createJob('email'));

        $claimed = $this->repository->claimNext('default');

        $this->assertNull($claimed);
    }

    public function test_claim_next_skips_processing_jobs(): void
    {
        $job = $this->createJob();
        $this->repository->save($job);

        $this->repository->claimNext('default');
        $claimed = $this->repository->claimNext('default');

        $this->assertNull($claimed);
    }

    public function test_update_modifies_job(): void
    {
        $job = $this->createJob();
        $this->repository->save($job);

        $claimed = $this->repository->claimNext('default');
        $this->assertNotNull($claimed);

        $claimed->setStatus('completed');
        $claimed->setCompletedAt(new DateTimeImmutable());
        $this->repository->update($claimed);

        $this->assertSame(0, $this->repository->countByStatus('processing'));
        $this->assertSame(1, $this->repository->countByStatus('completed'));
    }

    public function test_find_failed_returns_failed_jobs(): void
    {
        $job = $this->createJob();
        $this->repository->save($job);

        $claimed = $this->repository->claimNext('default');
        $this->assertNotNull($claimed);

        $claimed->setStatus('failed');
        $claimed->setErrorMessage('Something went wrong');
        $this->repository->update($claimed);

        $failed = $this->repository->findFailed();

        $this->assertCount(1, $failed);
        $this->assertSame('failed', $failed[0]->getStatus());
        $this->assertSame('Something went wrong', $failed[0]->getErrorMessage());
    }

    public function test_count_by_status(): void
    {
        $this->repository->save($this->createJob());
        $this->repository->save($this->createJob());

        $this->assertSame(2, $this->repository->countByStatus('pending'));
        $this->assertSame(0, $this->repository->countByStatus('completed'));
    }

    public function test_count_pending(): void
    {
        $this->repository->save($this->createJob());
        $this->repository->save($this->createJob());
        $this->repository->save($this->createJob());

        $this->assertSame(3, $this->repository->countPending());
    }

    public function test_claim_next_does_not_return_future_jobs(): void
    {
        $futureJob = new QueuedJob(
            id: null,
            queue: 'default',
            jobClass: 'App\\Jobs\\TestJob',
            payload: '{}',
            status: 'pending',
            attempts: 0,
            maxAttempts: 3,
            errorMessage: null,
            availableAt: new DateTimeImmutable('+1 hour'),
            createdAt: new DateTimeImmutable(),
            completedAt: null,
        );
        $this->repository->save($futureJob);

        $claimed = $this->repository->claimNext('default');

        $this->assertNull($claimed);
    }

    private function createJob(string $queue = 'default'): QueuedJob
    {
        $now = new DateTimeImmutable();

        return new QueuedJob(
            id: null,
            queue: $queue,
            jobClass: 'App\\Jobs\\TestJob',
            payload: '{}',
            status: 'pending',
            attempts: 0,
            maxAttempts: 3,
            errorMessage: null,
            availableAt: $now,
            createdAt: $now,
            completedAt: null,
        );
    }
}
