<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Database;

use App\Database\Grammar\SqliteGrammar;
use PHPUnit\Framework\TestCase;

final class SqliteGrammarTest extends TestCase
{
    private SqliteGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grammar = new SqliteGrammar();
    }

    public function test_compile_select_all(): void
    {
        $sql = $this->grammar->compileSelect('users', [], [], [], null, null, []);

        $this->assertSame('SELECT * FROM users', $sql);
    }

    public function test_compile_select_with_columns(): void
    {
        $sql = $this->grammar->compileSelect('users', ['id', 'email'], [], [], null, null, []);

        $this->assertSame('SELECT id, email FROM users', $sql);
    }

    public function test_compile_select_with_where(): void
    {
        $sql = $this->grammar->compileSelect(
            'users',
            [],
            [['column' => 'email', 'operator' => '=']],
            [],
            null,
            null,
            [],
        );

        $this->assertSame('SELECT * FROM users WHERE email = ?', $sql);
    }

    public function test_compile_select_with_multiple_wheres(): void
    {
        $sql = $this->grammar->compileSelect(
            'users',
            [],
            [
                ['column' => 'role', 'operator' => '='],
                ['column' => 'enabled', 'operator' => '='],
            ],
            [],
            null,
            null,
            [],
        );

        $this->assertSame('SELECT * FROM users WHERE role = ? AND enabled = ?', $sql);
    }

    public function test_compile_select_with_order(): void
    {
        $sql = $this->grammar->compileSelect(
            'users',
            [],
            [],
            [['column' => 'name', 'direction' => 'ASC']],
            null,
            null,
            [],
        );

        $this->assertSame('SELECT * FROM users ORDER BY name ASC', $sql);
    }

    public function test_compile_select_with_limit(): void
    {
        $sql = $this->grammar->compileSelect('users', [], [], [], 10, null, []);

        $this->assertSame('SELECT * FROM users LIMIT 10', $sql);
    }

    public function test_compile_select_with_limit_and_offset(): void
    {
        $sql = $this->grammar->compileSelect('users', [], [], [], 10, 20, []);

        $this->assertSame('SELECT * FROM users LIMIT 10 OFFSET 20', $sql);
    }

    public function test_compile_select_with_join(): void
    {
        $sql = $this->grammar->compileSelect(
            'audits',
            [],
            [],
            [],
            null,
            null,
            [['table' => 'urls', 'first' => 'audits.url_id', 'second' => 'urls.id', 'type' => 'INNER']],
        );

        $this->assertSame('SELECT * FROM audits INNER JOIN urls ON audits.url_id = urls.id', $sql);
    }

    public function test_compile_count(): void
    {
        $sql = $this->grammar->compileCount('users', []);

        $this->assertSame('SELECT COUNT(*) AS aggregate FROM users', $sql);
    }

    public function test_compile_count_with_where(): void
    {
        $sql = $this->grammar->compileCount('users', [['column' => 'role', 'operator' => '=']]);

        $this->assertSame('SELECT COUNT(*) AS aggregate FROM users WHERE role = ?', $sql);
    }

    public function test_compile_insert(): void
    {
        $sql = $this->grammar->compileInsert('users', ['email', 'role']);

        $this->assertSame('INSERT INTO users (email, role) VALUES (?, ?)', $sql);
    }

    public function test_compile_insert_ignore(): void
    {
        $sql = $this->grammar->compileInsertIgnore('users', ['email', 'role']);

        $this->assertSame('INSERT OR IGNORE INTO users (email, role) VALUES (?, ?)', $sql);
    }

    public function test_compile_update(): void
    {
        $sql = $this->grammar->compileUpdate(
            'users',
            ['email', 'updated_at'],
            [['column' => 'id', 'operator' => '=']],
        );

        $this->assertSame('UPDATE users SET email = ?, updated_at = ? WHERE id = ?', $sql);
    }

    public function test_compile_delete(): void
    {
        $sql = $this->grammar->compileDelete(
            'users',
            [['column' => 'id', 'operator' => '=']],
        );

        $this->assertSame('DELETE FROM users WHERE id = ?', $sql);
    }

    public function test_compile_select_full_query(): void
    {
        $sql = $this->grammar->compileSelect(
            'audit_comparisons AS ac',
            ['ac.id', 'a.score'],
            [['column' => 'a.url_id', 'operator' => '=']],
            [['column' => 'ac.created_at', 'direction' => 'DESC']],
            5,
            null,
            [['table' => 'audits AS a', 'first' => 'ac.current_audit_id', 'second' => 'a.id', 'type' => 'INNER']],
        );

        $this->assertSame(
            'SELECT ac.id, a.score FROM audit_comparisons AS ac INNER JOIN audits AS a ON ac.current_audit_id = a.id WHERE a.url_id = ? ORDER BY ac.created_at DESC LIMIT 5',
            $sql,
        );
    }
}
