<?php

declare(strict_types=1);

namespace App\Database\Grammar;

use Override;

final readonly class SqliteGrammar implements GrammarInterface
{
    #[Override]
    public function compileSelect(
        string $table,
        array $columns,
        array $wheres,
        array $orders,
        ?int $limit,
        ?int $offset,
        array $joins,
    ): string {
        $sql = 'SELECT ' . ($columns === [] ? '*' : implode(', ', $columns));
        $sql .= ' FROM ' . $table;
        $sql .= $this->compileJoins($joins);
        $sql .= $this->compileWheres($wheres);
        $sql .= $this->compileOrders($orders);

        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }

        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }

        return $sql;
    }

    #[Override]
    public function compileCount(string $table, array $wheres): string
    {
        return 'SELECT COUNT(*) AS aggregate FROM ' . $table . $this->compileWheres($wheres);
    }

    #[Override]
    public function compileInsert(string $table, array $columns): string
    {
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        return 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
    }

    #[Override]
    public function compileInsertIgnore(string $table, array $columns): string
    {
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        return 'INSERT OR IGNORE INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
    }

    #[Override]
    public function compileUpdate(string $table, array $columns, array $wheres): string
    {
        $sets = array_map(static fn (string $column): string => $column . ' = ?', $columns);

        return 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . $this->compileWheres($wheres);
    }

    #[Override]
    public function compileDelete(string $table, array $wheres): string
    {
        return 'DELETE FROM ' . $table . $this->compileWheres($wheres);
    }

    /**
     * @param list<array{column: string, operator: string}> $wheres
     */
    private function compileWheres(array $wheres): string
    {
        if ($wheres === []) {
            return '';
        }

        $clauses = array_map(
            static fn (array $where): string => $where['column'] . ' ' . $where['operator'] . ' ?',
            $wheres,
        );

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    /**
     * @param list<array{column: string, direction: string}> $orders
     */
    private function compileOrders(array $orders): string
    {
        if ($orders === []) {
            return '';
        }

        $clauses = array_map(
            static fn (array $order): string => $order['column'] . ' ' . $order['direction'],
            $orders,
        );

        return ' ORDER BY ' . implode(', ', $clauses);
    }

    /**
     * @param list<array{table: string, first: string, second: string, type: string}> $joins
     */
    private function compileJoins(array $joins): string
    {
        if ($joins === []) {
            return '';
        }

        $clauses = array_map(
            static fn (array $join): string => ' ' . $join['type'] . ' JOIN ' . $join['table'] . ' ON ' . $join['first'] . ' = ' . $join['second'],
            $joins,
        );

        return implode('', $clauses);
    }
}
