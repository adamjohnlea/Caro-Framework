<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\HomeController;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class HomeControllerTest extends TestCase
{
    public function test_index_renders_home_view_with_enabled_modules(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('home/index.twig', [
                'modules' => [
                    ['label' => 'Authentication', 'enabled' => true],
                    ['label' => 'Email', 'enabled' => true],
                    ['label' => 'Queue', 'enabled' => false],
                ],
            ])
            ->willReturn('<html>Home Page</html>');

        $controller = new HomeController($twig, [
            'auth' => true,
            'email' => true,
            'queue' => false,
        ]);

        $response = $controller->index();

        $this->assertSame('<html>Home Page</html>', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_index_renders_with_all_modules_disabled(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('home/index.twig', [
                'modules' => [
                    ['label' => 'Authentication', 'enabled' => false],
                    ['label' => 'Email', 'enabled' => false],
                    ['label' => 'Queue', 'enabled' => false],
                ],
            ])
            ->willReturn('<html>Home Page</html>');

        $controller = new HomeController($twig, [
            'auth' => false,
            'email' => false,
            'queue' => false,
        ]);

        $response = $controller->index();

        $this->assertSame('<html>Home Page</html>', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_index_handles_missing_module_keys(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('home/index.twig', [
                'modules' => [
                    ['label' => 'Authentication', 'enabled' => false],
                    ['label' => 'Email', 'enabled' => false],
                    ['label' => 'Queue', 'enabled' => false],
                ],
            ])
            ->willReturn('<html>Home Page</html>');

        $controller = new HomeController($twig, []);

        $response = $controller->index();

        $this->assertSame('<html>Home Page</html>', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }
}
