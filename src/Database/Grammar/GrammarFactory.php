<?php

declare(strict_types=1);

namespace App\Database\Grammar;

use InvalidArgumentException;

final class GrammarFactory
{
    /**
     * Create a grammar instance for the given database driver.
     */
    public static function create(string $driver): GrammarInterface
    {
        return match ($driver) {
            'sqlite' => new SqliteGrammar(),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported database driver: "%s". Supported drivers: sqlite', $driver),
            ),
        };
    }

    /**
     * Get list of supported database drivers.
     *
     * @return list<string>
     */
    public static function getSupportedDrivers(): array
    {
        return ['sqlite'];
    }
}
