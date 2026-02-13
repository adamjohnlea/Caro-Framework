<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class HomeController
{
    /**
     * @param array<string, bool> $enabledModules
     */
    public function __construct(
        private Environment $twig,
        private array $enabledModules,
    ) {
    }

    public function index(): Response
    {
        $modules = [
            ['label' => 'Authentication', 'enabled' => $this->enabledModules['auth'] ?? false],
            ['label' => 'Email', 'enabled' => $this->enabledModules['email'] ?? false],
            ['label' => 'Queue', 'enabled' => $this->enabledModules['queue'] ?? false],
        ];

        return new Response(
            $this->twig->render('home/index.twig', [
                'modules' => $modules,
            ]),
        );
    }
}
