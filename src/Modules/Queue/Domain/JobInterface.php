<?php

declare(strict_types=1);

namespace App\Modules\Queue\Domain;

interface JobInterface
{
    public function handle(): void;

    public function getQueue(): string;

    public function getMaxAttempts(): int;
}
