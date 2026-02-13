<?php

declare(strict_types=1);

namespace App\Modules\Queue\Application\Services;

use App\Modules\Queue\Domain\JobInterface;
use App\Modules\Queue\Domain\Models\QueuedJob;
use App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class QueueService
{
    public function __construct(
        private QueueRepositoryInterface $queueRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(JobInterface $job): QueuedJob
    {
        $now = new DateTimeImmutable();

        $queuedJob = new QueuedJob(
            id: null,
            queue: $job->getQueue(),
            jobClass: $job::class,
            payload: json_encode($job, JSON_THROW_ON_ERROR),
            status: 'pending',
            attempts: 0,
            maxAttempts: $job->getMaxAttempts(),
            errorMessage: null,
            availableAt: $now,
            createdAt: $now,
            completedAt: null,
        );

        return $this->queueRepository->save($queuedJob);
    }

    public function processNext(string $queue = 'default'): bool
    {
        $job = $this->queueRepository->claimNext($queue);

        if (!$job instanceof QueuedJob) {
            return false;
        }

        $jobClass = $job->getJobClass();

        if (!class_exists($jobClass)) {
            $job->setStatus('failed');
            $job->setErrorMessage('Job class not found: ' . $jobClass);
            $this->queueRepository->update($job);
            $this->logger->error('Queue job class not found: ' . $jobClass);

            return true;
        }

        try {
            /** @var JobInterface $jobInstance */
            $jobInstance = new $jobClass();
            $jobInstance->handle();

            $job->setStatus('completed');
            $job->setCompletedAt(new DateTimeImmutable());
            $this->queueRepository->update($job);

            $this->logger->info('Queue job completed: ' . $jobClass);

            return true;
        } catch (Throwable $e) {
            if ($job->getAttempts() >= $job->getMaxAttempts()) {
                $job->setStatus('failed');
                $job->setErrorMessage($e->getMessage());
                $this->queueRepository->update($job);

                $this->logger->error('Queue job failed permanently: ' . $jobClass, [
                    'error' => $e->getMessage(),
                    'attempts' => $job->getAttempts(),
                ]);
            } else {
                $job->setStatus('pending');
                $job->setErrorMessage($e->getMessage());
                $this->queueRepository->update($job);

                $this->logger->warning('Queue job failed, will retry: ' . $jobClass, [
                    'error' => $e->getMessage(),
                    'attempts' => $job->getAttempts(),
                    'max_attempts' => $job->getMaxAttempts(),
                ]);
            }

            return true;
        }
    }

    public function retryFailed(): int
    {
        $failedJobs = $this->queueRepository->findFailed();

        foreach ($failedJobs as $job) {
            $job->setStatus('pending');
            $job->setAttempts(0);
            $job->setErrorMessage(null);
            $this->queueRepository->update($job);
        }

        $count = count($failedJobs);
        if ($count > 0) {
            $this->logger->info('Retrying ' . $count . ' failed job(s)');
        }

        return $count;
    }

    public function countPending(): int
    {
        return $this->queueRepository->countPending();
    }
}
