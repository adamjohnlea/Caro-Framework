<?php

declare(strict_types=1);

namespace App\Database;

use App\Database\Grammar\GrammarFactory;
use RuntimeException;

final class DatabaseFactory
{
    /**
     * @param array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string} $config
     */
    public static function create(array $config): Database
    {
        $driver = $config['driver'];
        $grammar = GrammarFactory::create($driver);

        return match ($driver) {
            'sqlite' => new Database(self::buildSqliteDsn($config['path']), '', '', $grammar),
            'pgsql' => new Database(self::buildPgsqlDsn($config), $config['user'], $config['password'], $grammar),
            default => throw new RuntimeException(sprintf('Unsupported database driver: %s', $driver)),
        };
    }

    private static function buildSqliteDsn(string $path): string
    {
        return 'sqlite:' . $path;
    }

    /**
     * @param array{host: string, port: string, name: string} $config
     */
    private static function buildPgsqlDsn(array $config): string
    {
        return sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['port'],
            $config['name'],
        );
    }
}
