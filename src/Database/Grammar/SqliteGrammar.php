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
        $quotedColumns = array_map($this->quoteIdentifier(...), $columns);
        $sql = 'SELECT ' . ($columns === [] ? '*' : implode(', ', $quotedColumns));
        $sql .= ' FROM ' . $this->quoteIdentifier($table);
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
        return 'SELECT COUNT(*) AS aggregate FROM ' . $this->quoteIdentifier($table) . $this->compileWheres($wheres);
    }

    #[Override]
    public function compileInsert(string $table, array $columns): string
    {
        $quotedColumns = array_map($this->quoteIdentifier(...), $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        return 'INSERT INTO ' . $this->quoteIdentifier($table) . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . $placeholders . ')';
    }

    #[Override]
    public function compileInsertIgnore(string $table, array $columns): string
    {
        $quotedColumns = array_map($this->quoteIdentifier(...), $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        return 'INSERT OR IGNORE INTO ' . $this->quoteIdentifier($table) . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . $placeholders . ')';
    }

    #[Override]
    public function compileUpdate(string $table, array $columns, array $wheres): string
    {
        $sets = array_map(fn (string $column): string => $this->quoteIdentifier($column) . ' = ?', $columns);

        return 'UPDATE ' . $this->quoteIdentifier($table) . ' SET ' . implode(', ', $sets) . $this->compileWheres($wheres);
    }

    #[Override]
    public function compileDelete(string $table, array $wheres): string
    {
        return 'DELETE FROM ' . $this->quoteIdentifier($table) . $this->compileWheres($wheres);
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
            fn (array $where): string => $this->quoteIdentifier($where['column']) . ' ' . $where['operator'] . ' ?',
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
            fn (array $order): string => $this->quoteIdentifier($order['column']) . ' ' . $order['direction'],
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
            fn (array $join): string => ' ' . $join['type'] . ' JOIN ' . $this->quoteIdentifier($join['table']) . ' ON ' . $this->quoteIdentifier($join['first']) . ' = ' . $this->quoteIdentifier($join['second']),
            $joins,
        );

        return implode('', $clauses);
    }

    /**
     * Quote an identifier (table or column name) to prevent SQL injection.
     * SQLite supports double quotes for identifiers.
     * Handles table aliases (e.g., "users AS u") and qualified names (e.g., "u.id").
     */
    private function quoteIdentifier(string $identifier): string
    {
        // Handle table aliases like "users AS u"
        if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $identifier, $matches) === 1) {
            return $this->quoteIdentifier(trim($matches[1])) . ' AS ' . $this->quoteIdentifier(trim($matches[2]));
        }

        // Handle qualified identifiers like "table.column"
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);

            return implode('.', array_map(fn (string $part): string => '"' . str_replace('"', '""', $part) . '"', $parts));
        }

        // Escape any existing double quotes by doubling them
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
