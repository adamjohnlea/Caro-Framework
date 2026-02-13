<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Cli;

use App\Database\Database;
use App\Shared\Cli\CliBootstrap;
use App\Shared\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CliBootstrapTest extends TestCase
{
    public function test_returns_container_interface(): void
    {
        $config = $this->getConfig();

        $container = CliBootstrap::createContainer($config);

        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    public function test_core_services_are_registered(): void
    {
        $config = $this->getConfig();

        $container = CliBootstrap::createContainer($config);

        $this->assertTrue($container->has(LoggerInterface::class));
        $this->assertTrue($container->has(Database::class));
    }

    public function test_auth_module_conditionally_registered(): void
    {
        $config = $this->getConfig();
        $config['modules']['auth'] = true;

        $container = CliBootstrap::createContainer($config);

        $this->assertCount(1, $container->getProviders());
    }

    public function test_no_modules_when_all_disabled(): void
    {
        $config = $this->getConfig();

        $container = CliBootstrap::createContainer($config);

        $this->assertCount(0, $container->getProviders());
    }

    /**
     * @return array{app: array{env: string, debug: bool, name: string}, database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}, ses: array{region: string, access_key: string, secret_key: string, from_address: string}}
     */
    private function getConfig(): array
    {
        return [
            'app' => ['env' => 'testing', 'debug' => false, 'name' => 'Test'],
            'database' => [
                'driver' => 'sqlite',
                'path' => ':memory:',
                'host' => '',
                'port' => '',
                'name' => '',
                'user' => '',
                'password' => '',
            ],
            'modules' => ['auth' => false, 'email' => false, 'queue' => false],
            'ses' => ['region' => '', 'access_key' => '', 'secret_key' => '', 'from_address' => ''],
        ];
    }
}
