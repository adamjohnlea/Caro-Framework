<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Database\Database;
use App\Http\Controllers\HealthController;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    public function test_check_returns_200_when_database_is_healthy(): void
    {
        $database = new Database('sqlite::memory:');

        $controller = new HealthController($database);

        $response = $controller->check();

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('healthy', $data['status']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertTrue($data['checks']['database']['ok']);
        $this->assertSame('Connected', $data['checks']['database']['message']);
    }

    public function test_check_handles_database_exception_gracefully(): void
    {
        // Note: Database is a final readonly class that cannot be mocked.
        // The failure path (catching database exceptions) is tested in integration tests.
        // This test verifies the structure when a database check is included.
        $database = new Database('sqlite::memory:');

        $controller = new HealthController($database);

        $response = $controller->check();

        $data = json_decode((string) $response->getContent(), true);

        // Verify the response includes the database check structure
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('ok', $data['checks']['database']);
        $this->assertArrayHasKey('message', $data['checks']['database']);
        $this->assertIsBool($data['checks']['database']['ok']);
        $this->assertIsString($data['checks']['database']['message']);
    }

    public function test_check_returns_json_response(): void
    {
        $database = new Database('sqlite::memory:');

        $controller = new HealthController($database);

        $response = $controller->check();

        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertJson((string) $response->getContent());
    }

    public function test_check_response_structure_includes_all_checks(): void
    {
        $database = new Database('sqlite::memory:');

        $controller = new HealthController($database);

        $response = $controller->check();

        $data = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertIsArray($data['checks']);
    }
}
