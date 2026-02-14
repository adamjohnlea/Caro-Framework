<?php

declare(strict_types=1);

namespace App\Modules\Queue\Application\Services;

use App\Modules\Queue\Domain\Exceptions\InvalidJobException;
use App\Modules\Queue\Domain\JobInterface;
use App\Modules\Queue\Domain\Models\QueuedJob;
use App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface;
use App\Shared\Container\ContainerInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class QueueService
{
    public function __construct(
        private QueueRepositoryInterface $queueRepository,
        private LoggerInterface $logger,
        private ContainerInterface $container,
    ) {
    }

    public function dispatch(JobInterface $job): QueuedJob
    {
        $now = new DateTimeImmutable();

        $queuedJob = new QueuedJob(
            id: null,
            queue: $job->getQueue(),
            jobClass: $job::class,
            payload: serialize($job),
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

        // Validate job class implements JobInterface BEFORE unserializing
        if (!is_subclass_of($jobClass, JobInterface::class)) {
            $job->setStatus('failed');
            $job->setErrorMessage('Job class does not implement JobInterface: ' . $jobClass);
            $this->queueRepository->update($job);
            $this->logger->error('Queue job class does not implement JobInterface: ' . $jobClass);

            return true;
        }

        try {
            $payload = $job->getPayload();
            // Only allow unserializing the specific job class (prevents object injection attacks)
            $jobInstance = unserialize($payload, ['allowed_classes' => [$jobClass]]);

            if (!$jobInstance instanceof JobInterface) {
                throw InvalidJobException::notAnInstance($jobClass);
            }

            $jobInstance->handle($this->container);

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
