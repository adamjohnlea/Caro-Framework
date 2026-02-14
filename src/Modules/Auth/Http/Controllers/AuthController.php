<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\UrlGenerator;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Domain\Models\User;
use App\Shared\Session\FlashMessageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class AuthController
{
    public function __construct(
        private AuthenticationService $authService,
        private Environment $twig,
        private FlashMessageService $flashMessageService,
        private UrlGenerator $urlGenerator,
    ) {
    }

    public function showLogin(): Response
    {
        $html = $this->twig->render('@auth/login.twig', [
            'csrf_token' => $this->authService->getCsrfToken(),
        ]);

        return new Response($html);
    }

    public function login(Request $request): Response
    {
        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->authService->validateCsrfToken($token)) {
            $html = $this->twig->render('@auth/login.twig', [
                'error' => 'Invalid request. Please try again.',
                'csrf_token' => $this->authService->getCsrfToken(),
            ]);

            return new Response($html, 422);
        }

        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');

        $user = $this->authService->attempt($email, $password);

        if (!$user instanceof User) {
            $html = $this->twig->render('@auth/login.twig', [
                'error' => 'Invalid email or password.',
                'csrf_token' => $this->authService->getCsrfToken(),
                'old' => ['email' => $email],
            ]);

            return new Response($html, 422);
        }

        $this->flashMessageService->flash('success', 'You have been logged in.');

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function logout(): RedirectResponse
    {
        $this->authService->logout();

        $this->flashMessageService->flash('success', 'You have been logged out.');

        return new RedirectResponse($this->urlGenerator->generate('login'));
    }
}
