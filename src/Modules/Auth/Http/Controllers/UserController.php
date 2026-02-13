<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Shared\Exceptions\ValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class UserController
{
    public function __construct(
        private UserService $userService,
        private AuthenticationService $authService,
        private Environment $twig,
    ) {
    }

    public function index(): Response
    {
        $users = $this->userService->findAll();

        $html = $this->twig->render('users/index.twig', [
            'users' => $users,
        ]);

        return new Response($html);
    }

    public function create(): Response
    {
        $html = $this->twig->render('users/create.twig', [
            'roles' => UserRole::cases(),
        ]);

        return new Response($html);
    }

    public function store(Request $request): Response
    {
        try {
            $this->userService->create(
                email: (string) $request->request->get('email', ''),
                password: (string) $request->request->get('password', ''),
                role: (string) $request->request->get('role', 'viewer'),
            );

            return new RedirectResponse('/users');
        } catch (ValidationException $e) {
            $html = $this->twig->render('users/create.twig', [
                'error' => $e->getMessage(),
                'roles' => UserRole::cases(),
                'old' => $request->request->all(),
            ]);

            return new Response($html, 422);
        }
    }

    public function edit(int $id): Response
    {
        $user = $this->userService->findById($id);

        if (!$user instanceof User) {
            return new Response('Not Found', 404);
        }

        $html = $this->twig->render('users/edit.twig', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);

        return new Response($html);
    }

    public function update(int $id, Request $request): Response
    {
        try {
            $password = (string) $request->request->get('password', '');

            $this->userService->update(
                id: $id,
                email: (string) $request->request->get('email', ''),
                password: $password !== '' ? $password : null,
                role: (string) $request->request->get('role', 'viewer'),
            );

            return new RedirectResponse('/users');
        } catch (ValidationException $e) {
            $user = $this->userService->findById($id);

            if (!$user instanceof User) {
                return new Response('Not Found', 404);
            }

            $html = $this->twig->render('users/edit.twig', [
                'error' => $e->getMessage(),
                'user' => $user,
                'roles' => UserRole::cases(),
            ]);

            return new Response($html, 422);
        }
    }

    public function destroy(int $id): Response
    {
        $currentUser = $this->authService->getCurrentUser();

        if ($currentUser instanceof User && $currentUser->getId() === $id) {
            return new Response('You cannot delete your own account.', 403);
        }

        try {
            $this->userService->delete($id);

            return new RedirectResponse('/users');
        } catch (ValidationException) {
            return new Response('Not Found', 404);
        }
    }
}
