<?php

declare(strict_types=1);

namespace App\Modules\Queue\Domain;

use App\Shared\Container\ContainerInterface;

interface JobInterface
{
    public function handle(ContainerInterface $container): void;

    public function getQueue(): string;

    public function getMaxAttempts(): int;
}
