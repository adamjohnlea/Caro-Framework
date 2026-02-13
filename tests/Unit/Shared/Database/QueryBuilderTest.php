<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Database;

use App\Database\Grammar\SqliteGrammar;
use App\Shared\Database\QueryBuilder;
use Tests\TestCase;

final class QueryBuilderTest extends TestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                role TEXT NOT NULL DEFAULT \'viewer\',
                created_at TEXT NOT NULL
            )',
        );

        $this->builder = new QueryBuilder($this->database, new SqliteGrammar());
    }

    public function test_insert_and_get(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $rows = $this->builder->table('users')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('alice@example.com', $rows[0]['email']);
    }

    public function test_first_returns_single_row(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $this->builder->table('users')->insert([
            'email' => 'bob@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-02 00:00:00',
        ]);

        $row = $this->builder->table('users')->where('email', 'alice@example.com')->first();

        $this->assertNotNull($row);
        $this->assertSame('alice@example.com', $row['email']);
    }

    public function test_first_returns_null_when_no_results(): void
    {
        $row = $this->builder->table('users')->where('email', 'none@example.com')->first();

        $this->assertNull($row);
    }

    public function test_where_filters_results(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $this->builder->table('users')->insert([
            'email' => 'bob@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-02 00:00:00',
        ]);

        $rows = $this->builder->table('users')->where('role', 'admin')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('alice@example.com', $rows[0]['email']);
    }

    public function test_order_by(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'bob@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-02 00:00:00',
        ]);
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $rows = $this->builder->table('users')->orderBy('email')->get();

        $this->assertSame('alice@example.com', $rows[0]['email']);
        $this->assertSame('bob@example.com', $rows[1]['email']);
    }

    public function test_order_by_desc(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $this->builder->table('users')->insert([
            'email' => 'bob@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-02 00:00:00',
        ]);

        $rows = $this->builder->table('users')->orderBy('email', 'desc')->get();

        $this->assertSame('bob@example.com', $rows[0]['email']);
    }

    public function test_limit(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $this->builder->table('users')->insert([
            'email' => 'bob@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-02 00:00:00',
        ]);

        $rows = $this->builder->table('users')->limit(1)->get();

        $this->assertCount(1, $rows);
    }

    public function test_count(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $this->builder->table('users')->insert([
            'email' => 'bob@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-02 00:00:00',
        ]);

        $this->assertSame(2, $this->builder->table('users')->count());
        $this->assertSame(1, $this->builder->table('users')->where('role', 'admin')->count());
    }

    public function test_update(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $affected = $this->builder->table('users')
            ->where('email', 'alice@example.com')
            ->update(['role' => 'admin']);

        $this->assertSame(1, $affected);

        $row = $this->builder->table('users')->where('email', 'alice@example.com')->first();
        $this->assertNotNull($row);
        $this->assertSame('admin', $row['role']);
    }

    public function test_delete(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $affected = $this->builder->table('users')
            ->where('email', 'alice@example.com')
            ->delete();

        $this->assertSame(1, $affected);
        $this->assertSame(0, $this->builder->table('users')->count());
    }

    public function test_insert_ignore(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $this->builder->table('users')->insertIgnore([
            'email' => 'alice@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-02 00:00:00',
        ]);

        $this->assertSame(1, $this->builder->table('users')->count());
    }

    public function test_select_specific_columns(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $row = $this->builder->table('users')->select(['email', 'role'])->first();

        $this->assertNotNull($row);
        $this->assertSame('alice@example.com', $row['email']);
        $this->assertSame('admin', $row['role']);
        $this->assertArrayNotHasKey('id', $row);
    }

    public function test_immutability(): void
    {
        $base = $this->builder->table('users');
        $filtered = $base->where('role', 'admin');

        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $this->builder->table('users')->insert([
            'email' => 'bob@example.com',
            'role' => 'viewer',
            'created_at' => '2024-01-02 00:00:00',
        ]);

        $this->assertSame(2, $base->count());
        $this->assertSame(1, $filtered->count());
    }

    public function test_last_insert_id(): void
    {
        $this->builder->table('users')->insert([
            'email' => 'alice@example.com',
            'role' => 'admin',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $lastId = $this->builder->lastInsertId();

        $this->assertSame('1', $lastId);
    }
}
