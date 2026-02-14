<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\Grammar\GrammarFactory;
use App\Database\Grammar\GrammarInterface;
use App\Database\Grammar\SqliteGrammar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GrammarFactoryTest extends TestCase
{
    public function test_create_returns_sqlite_grammar_for_sqlite_driver(): void
    {
        $grammar = GrammarFactory::create('sqlite');

        $this->assertInstanceOf(GrammarInterface::class, $grammar);
        $this->assertInstanceOf(SqliteGrammar::class, $grammar);
    }

    public function test_create_throws_exception_for_unsupported_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver: "mysql"');

        GrammarFactory::create('mysql');
    }

    public function test_create_throws_exception_for_empty_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver: ""');

        GrammarFactory::create('');
    }

    public function test_get_supported_drivers_returns_array_of_drivers(): void
    {
        $drivers = GrammarFactory::getSupportedDrivers();

        $this->assertIsArray($drivers);
        $this->assertNotEmpty($drivers);
        $this->assertContains('sqlite', $drivers);
    }

    public function test_get_supported_drivers_contains_only_strings(): void
    {
        $drivers = GrammarFactory::getSupportedDrivers();

        foreach ($drivers as $driver) {
            $this->assertIsString($driver);
        }
    }

    public function test_all_supported_drivers_can_be_created(): void
    {
        $drivers = GrammarFactory::getSupportedDrivers();

        foreach ($drivers as $driver) {
            $grammar = GrammarFactory::create($driver);
            $this->assertInstanceOf(GrammarInterface::class, $grammar);
        }
    }
}
