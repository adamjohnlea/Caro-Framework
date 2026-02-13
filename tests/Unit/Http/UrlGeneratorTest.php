<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Router;
use App\Http\UrlGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UrlGeneratorTest extends TestCase
{
    public function test_generate_with_no_params(): void
    {
        $router = new Router();
        $router->get('/', 'HomeController', 'index', 'home');

        $generator = new UrlGenerator($router->getRoutes());

        $this->assertSame('/', $generator->generate('home'));
    }

    public function test_generate_with_params(): void
    {
        $router = new Router();
        $router->get('/users/{id}/edit', 'UserController', 'edit', 'users.edit');

        $generator = new UrlGenerator($router->getRoutes());

        $this->assertSame('/users/42/edit', $generator->generate('users.edit', ['id' => 42]));
    }

    public function test_missing_route_throws(): void
    {
        $router = new Router();
        $generator = new UrlGenerator($router->getRoutes());

        $this->expectException(RuntimeException::class);
        $generator->generate('nonexistent');
    }

    public function test_generate_with_multiple_params(): void
    {
        $router = new Router();
        $router->get('/posts/{postId}/comments/{commentId}', 'CommentController', 'show', 'comments.show');

        $generator = new UrlGenerator($router->getRoutes());

        $this->assertSame(
            '/posts/5/comments/10',
            $generator->generate('comments.show', ['postId' => 5, 'commentId' => 10]),
        );
    }
}
