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
        $this->database->query(
            'INSERT INTO jobs (queue, job_class, payload, status, attempts, max_attempts, error_message, available_at, created_at, completed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $job->getQueue(),
                $job->getJobClass(),
                $job->getPayload(),
                $job->getStatus(),
                $job->getAttempts(),
                $job->getMaxAttempts(),
                $job->getErrorMessage(),
                $job->getAvailableAt()->format('Y-m-d H:i:s'),
                $job->getCreatedAt()->format('Y-m-d H:i:s'),
                $job->getCompletedAt()?->format('Y-m-d H:i:s'),
            ],
        );

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $job->setId((int) $lastId);
        }

        return $job;
    }

    #[Override]
    public function update(QueuedJob $job): void
    {
        $this->database->query(
            'UPDATE jobs SET status = ?, attempts = ?, error_message = ?, completed_at = ? WHERE id = ?',
            [
                $job->getStatus(),
                $job->getAttempts(),
                $job->getErrorMessage(),
                $job->getCompletedAt()?->format('Y-m-d H:i:s'),
                $job->getId(),
            ],
        );
    }

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
        $stmt = $this->database->query(
            'SELECT * FROM jobs WHERE status = ? ORDER BY created_at ASC',
            ['failed'],
        );

        /** @var list<array{id: string|int, queue: string, job_class: string, payload: string, status: string, attempts: string|int, max_attempts: string|int, error_message: string|null, available_at: string, created_at: string, completed_at: string|null}> $rows */
        $rows = $stmt->fetchAll();

        return array_map($this->hydrateJob(...), $rows);
    }

    #[Override]
    public function countByStatus(string $status): int
    {
        $stmt = $this->database->query(
            'SELECT COUNT(*) as count FROM jobs WHERE status = ?',
            [$status],
        );

        /** @var array{count: string|int} $row */
        $row = $stmt->fetch();

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
