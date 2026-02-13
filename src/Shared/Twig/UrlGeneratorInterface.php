<?php

declare(strict_types=1);

namespace App\Shared\Twig;

interface UrlGeneratorInterface
{
    /**
     * @param array<string, int|string> $params
     */
    public function generate(string $name, array $params = []): string;
}
