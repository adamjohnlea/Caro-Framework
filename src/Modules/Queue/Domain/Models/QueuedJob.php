<?php

declare(strict_types=1);

namespace App\Modules\Queue\Domain\Models;

use DateTimeImmutable;

final class QueuedJob
{
    public function __construct(
        private ?int $id,
        private readonly string $queue,
        private readonly string $jobClass,
        private readonly string $payload,
        private string $status,
        private int $attempts,
        private readonly int $maxAttempts,
        private ?string $errorMessage,
        private readonly DateTimeImmutable $availableAt,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getJobClass(): string
    {
        return $this->jobClass;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getAvailableAt(): DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }
}
