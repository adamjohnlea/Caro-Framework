<?php

declare(strict_types=1);

namespace App\Shared\Database;

use App\Database\Database;
use App\Database\Grammar\GrammarInterface;

final readonly class QueryBuilder
{
    /**
     * @param list<string>                                                            $columns
     * @param list<array{column: string, operator: string, value: mixed}>             $wheres
     * @param list<array{column: string, direction: string}>                          $orders
     * @param list<array{table: string, first: string, second: string, type: string}> $joins
     * @param list<mixed>                                                             $bindings
     */
    public function __construct(
        private Database $database,
        private GrammarInterface $grammar,
        private string $table = '',
        private array $columns = [],
        private array $wheres = [],
        private array $orders = [],
        private ?int $limitValue = null,
        private ?int $offsetValue = null,
        private array $joins = [],
        private array $bindings = [],
    ) {
    }

    public function table(string $table): self
    {
        return new self(
            $this->database,
            $this->grammar,
            $table,
            $this->columns,
            $this->wheres,
            $this->orders,
            $this->limitValue,
            $this->offsetValue,
            $this->joins,
            $this->bindings,
        );
    }

    /**
     * @param list<string> $columns
     */
    public function select(array $columns): self
    {
        return new self(
            $this->database,
            $this->grammar,
            $this->table,
            $columns,
            $this->wheres,
            $this->orders,
            $this->limitValue,
            $this->offsetValue,
            $this->joins,
            $this->bindings,
        );
    }

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $wheres = $this->wheres;
        $wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];

        $bindings = $this->bindings;
        $bindings[] = $value;

        return new self(
            $this->database,
            $this->grammar,
            $this->table,
            $this->columns,
            $wheres,
            $this->orders,
            $this->limitValue,
            $this->offsetValue,
            $this->joins,
            $bindings,
        );
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $orders = $this->orders;
        $orders[] = ['column' => $column, 'direction' => strtoupper($direction)];

        return new self(
            $this->database,
            $this->grammar,
            $this->table,
            $this->columns,
            $this->wheres,
            $orders,
            $this->limitValue,
            $this->offsetValue,
            $this->joins,
            $this->bindings,
        );
    }

    public function limit(int $limit): self
    {
        return new self(
            $this->database,
            $this->grammar,
            $this->table,
            $this->columns,
            $this->wheres,
            $this->orders,
            $limit,
            $this->offsetValue,
            $this->joins,
            $this->bindings,
        );
    }

    public function offset(int $offset): self
    {
        return new self(
            $this->database,
            $this->grammar,
            $this->table,
            $this->columns,
            $this->wheres,
            $this->orders,
            $this->limitValue,
            $offset,
            $this->joins,
            $this->bindings,
        );
    }

    public function join(string $table, string $first, string $second, string $type = 'INNER'): self
    {
        $joins = $this->joins;
        $joins[] = ['table' => $table, 'first' => $first, 'second' => $second, 'type' => strtoupper($type)];

        return new self(
            $this->database,
            $this->grammar,
            $this->table,
            $this->columns,
            $this->wheres,
            $this->orders,
            $this->limitValue,
            $this->offsetValue,
            $joins,
            $this->bindings,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $whereConditions = array_map(
            static fn (array $where): array => ['column' => $where['column'], 'operator' => $where['operator']],
            $this->wheres,
        );

        $sql = $this->grammar->compileSelect(
            $this->table,
            $this->columns,
            $whereConditions,
            $this->orders,
            $this->limitValue,
            $this->offsetValue,
            $this->joins,
        );

        $stmt = $this->database->query($sql, $this->bindings);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $result = $this->limit(1)->get();

        return $result[0] ?? null;
    }

    public function count(): int
    {
        $whereConditions = array_map(
            static fn (array $where): array => ['column' => $where['column'], 'operator' => $where['operator']],
            $this->wheres,
        );

        $sql = $this->grammar->compileCount($this->table, $whereConditions);

        $stmt = $this->database->query($sql, $this->bindings);

        /** @var array{aggregate: string|int} $row */
        $row = $stmt->fetch();

        return (int) $row['aggregate'];
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(array $values): bool
    {
        $columns = array_keys($values);
        $sql = $this->grammar->compileInsert($this->table, $columns);

        $this->database->query($sql, array_values($values));

        return true;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insertIgnore(array $values): bool
    {
        $columns = array_keys($values);
        $sql = $this->grammar->compileInsertIgnore($this->table, $columns);

        $this->database->query($sql, array_values($values));

        return true;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        $columns = array_keys($values);

        $whereConditions = array_map(
            static fn (array $where): array => ['column' => $where['column'], 'operator' => $where['operator']],
            $this->wheres,
        );

        $sql = $this->grammar->compileUpdate($this->table, $columns, $whereConditions);

        $params = array_merge(array_values($values), $this->bindings);
        $stmt = $this->database->query($sql, $params);

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $whereConditions = array_map(
            static fn (array $where): array => ['column' => $where['column'], 'operator' => $where['operator']],
            $this->wheres,
        );

        $sql = $this->grammar->compileDelete($this->table, $whereConditions);

        $stmt = $this->database->query($sql, $this->bindings);

        return $stmt->rowCount();
    }

    public function lastInsertId(): string|false
    {
        return $this->database->lastInsertId();
    }
}
