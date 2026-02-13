<?php

declare(strict_types=1);

namespace App\Modules\Queue\Domain;

use App\Shared\Container\Container;

interface JobInterface
{
    public function handle(Container $container): void;

    public function getQueue(): string;

    public function getMaxAttempts(): int;
}
