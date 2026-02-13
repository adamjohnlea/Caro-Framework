<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Router;

/**
 * Auth Module Routes
 *
 * @param Router $router
 */
return static function (Router $router): void {
    // Authentication routes
    $router->get('/login', AuthController::class, 'showLogin', 'login');
    $router->post('/login', AuthController::class, 'login', 'login.post');
    $router->get('/logout', AuthController::class, 'logout', 'logout');

    // User management routes
    $router->get('/users', UserController::class, 'index', 'users.index');
    $router->get('/users/create', UserController::class, 'create', 'users.create');
    $router->post('/users', UserController::class, 'store', 'users.store');
    $router->get('/users/{id}/edit', UserController::class, 'edit', 'users.edit');
    $router->post('/users/{id}/update', UserController::class, 'update', 'users.update');
    $router->post('/users/{id}/delete', UserController::class, 'destroy', 'users.destroy');
};
