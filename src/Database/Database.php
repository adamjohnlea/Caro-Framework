<?php

declare(strict_types=1);

namespace App\Database;

use App\Database\Grammar\GrammarInterface;
use App\Shared\Database\QueryBuilder;
use PDO;
use PDOStatement;

final readonly class Database
{
    private PDO $pdo;
    private GrammarInterface $grammar;

    public function __construct(string $dsn, string $username = '', string $password = '', ?GrammarInterface $grammar = null)
    {
        $this->pdo = new PDO(
            $dsn,
            $username !== '' ? $username : null,
            $password !== '' ? $password : null,
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if (str_starts_with($dsn, 'sqlite:')) {
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
        }

        // Use provided grammar or default to SqliteGrammar for backward compatibility
        $this->grammar = $grammar ?? Grammar\GrammarFactory::create('sqlite');
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    public function exec(string $sql): int|false
    {
        return $this->pdo->exec($sql);
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function getDriverName(): string
    {
        /** @var string */
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function table(string $table): QueryBuilder
    {
        $builder = new QueryBuilder($this, $this->grammar);

        return $builder->table($table);
    }
}
