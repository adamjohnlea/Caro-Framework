<?php

declare(strict_types=1);

namespace App\Database\Grammar;

interface GrammarInterface
{
    /**
     * @param list<string>                                                            $columns
     * @param list<array{column: string, direction: string}>                          $orders
     * @param list<array{table: string, first: string, second: string, type: string}> $joins
     * @param list<array{column: string, operator: string}>                           $wheres
     */
    public function compileSelect(
        string $table,
        array $columns,
        array $wheres,
        array $orders,
        ?int $limit,
        ?int $offset,
        array $joins,
    ): string;

    /**
     * @param list<array{column: string, operator: string}> $wheres
     */
    public function compileCount(string $table, array $wheres): string;

    /**
     * @param list<string> $columns
     */
    public function compileInsert(string $table, array $columns): string;

    /**
     * @param list<string> $columns
     */
    public function compileInsertIgnore(string $table, array $columns): string;

    /**
     * @param list<string>                                  $columns
     * @param list<array{column: string, operator: string}> $wheres
     */
    public function compileUpdate(string $table, array $columns, array $wheres): string;

    /**
     * @param list<array{column: string, operator: string}> $wheres
     */
    public function compileDelete(string $table, array $wheres): string;
}
