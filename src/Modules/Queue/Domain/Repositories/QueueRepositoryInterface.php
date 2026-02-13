<?php

declare(strict_types=1);

namespace App\Modules\Queue\Domain\Repositories;

use App\Modules\Queue\Domain\Models\QueuedJob;

interface QueueRepositoryInterface
{
    public function save(QueuedJob $job): QueuedJob;

    public function update(QueuedJob $job): void;

    public function claimNext(string $queue): ?QueuedJob;

    /**
     * @return list<QueuedJob>
     */
    public function findFailed(): array;

    public function countByStatus(string $status): int;

    public function countPending(): int;
}
