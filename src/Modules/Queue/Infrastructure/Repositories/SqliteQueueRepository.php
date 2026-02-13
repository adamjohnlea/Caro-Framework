<?php

declare(strict_types=1);

namespace App\Modules\Queue\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Queue\Domain\Models\QueuedJob;
use App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface;
use DateTimeImmutable;
use Override;
use Throwable;

final readonly class SqliteQueueRepository implements QueueRepositoryInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    #[Override]
    public function save(QueuedJob $job): QueuedJob
    {
        $this->database->table('jobs')->insert([
            'queue' => $job->getQueue(),
            'job_class' => $job->getJobClass(),
            'payload' => $job->getPayload(),
            'status' => $job->getStatus(),
            'attempts' => $job->getAttempts(),
            'max_attempts' => $job->getMaxAttempts(),
            'error_message' => $job->getErrorMessage(),
            'available_at' => $job->getAvailableAt()->format('Y-m-d H:i:s'),
            'created_at' => $job->getCreatedAt()->format('Y-m-d H:i:s'),
            'completed_at' => $job->getCompletedAt()?->format('Y-m-d H:i:s'),
        ]);

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $job->setId((int) $lastId);
        }

        return $job;
    }

    #[Override]
    public function update(QueuedJob $job): void
    {
        $this->database->table('jobs')
            ->where('id', $job->getId(), '=')
            ->update([
                'status' => $job->getStatus(),
                'attempts' => $job->getAttempts(),
                'error_message' => $job->getErrorMessage(),
                'completed_at' => $job->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Complex transaction-based query - keeping raw SQL for clarity and atomicity.
     * This SELECT + UPDATE must be atomic to prevent double-processing of jobs.
     */
    #[Override]
    public function claimNext(string $queue): ?QueuedJob
    {
        $this->database->beginTransaction();

        try {
            $nowDate = new DateTimeImmutable();
            $now = $nowDate->format('Y-m-d H:i:s');

            $stmt = $this->database->query(
                'SELECT * FROM jobs WHERE queue = ? AND status = ? AND available_at <= ? ORDER BY created_at ASC LIMIT 1',
                [$queue, 'pending', $now],
            );

            /** @var array{id: string|int, queue: string, job_class: string, payload: string, status: string, attempts: string|int, max_attempts: string|int, error_message: string|null, available_at: string, created_at: string, completed_at: string|null}|false $row */
            $row = $stmt->fetch();

            if ($row === false) {
                $this->database->commit();
                return null;
            }

            $this->database->query(
                'UPDATE jobs SET status = ?, attempts = attempts + 1 WHERE id = ?',
                ['processing', $row['id']],
            );

            $this->database->commit();

            return $this->hydrateJob([
                ...$row,
                'status' => 'processing',
                'attempts' => (int) $row['attempts'] + 1,
            ]);
        } catch (Throwable $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    /**
     * @return list<QueuedJob>
     */
    #[Override]
    public function findFailed(): array
    {
        /** @var list<array{id: string|int, queue: string, job_class: string, payload: string, status: string, attempts: string|int, max_attempts: string|int, error_message: string|null, available_at: string, created_at: string, completed_at: string|null}> $rows */
        $rows = $this->database->table('jobs')
            ->where('status', 'failed', '=')
            ->orderBy('created_at', 'ASC')
            ->get();

        return array_map($this->hydrateJob(...), $rows);
    }

    #[Override]
    public function countByStatus(string $status): int
    {
        $rows = $this->database->table('jobs')
            ->select(['COUNT(*) as count'])
            ->where('status', $status, '=')
            ->get();

        /** @var array{count: string|int} $row */
        $row = $rows[0];

        return (int) $row['count'];
    }

    #[Override]
    public function countPending(): int
    {
        return $this->countByStatus('pending');
    }

    /**
     * @param array{id: string|int, queue: string, job_class: string, payload: string, status: string, attempts: string|int, max_attempts: string|int, error_message: string|null, available_at: string, created_at: string, completed_at: string|null} $row
     */
    private function hydrateJob(array $row): QueuedJob
    {
        return new QueuedJob(
            id: (int) $row['id'],
            queue: $row['queue'],
            jobClass: $row['job_class'],
            payload: $row['payload'],
            status: $row['status'],
            attempts: (int) $row['attempts'],
            maxAttempts: (int) $row['max_attempts'],
            errorMessage: $row['error_message'],
            availableAt: new DateTimeImmutable($row['available_at']),
            createdAt: new DateTimeImmutable($row['created_at']),
            completedAt: $row['completed_at'] !== null ? new DateTimeImmutable($row['completed_at']) : null,
        );
    }
}
