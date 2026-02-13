<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Session\FlashMessageService;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    /**
     * @param callable(): string|null $csrfTokenProvider
     */
    public function __construct(
        private readonly string $publicPath,
        private readonly ?FlashMessageService $flashMessageService = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        private $csrfTokenProvider = null,
    ) {
    }

    /** @return list<TwigFunction> */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', $this->asset(...)),
            new TwigFunction('flash_messages', $this->flashMessages(...)),
            new TwigFunction('has_flash', $this->hasFlash(...)),
            new TwigFunction('get_flash_type', $this->getFlashType(...)),
            new TwigFunction('get_flash_message', $this->getFlashMessage(...)),
            new TwigFunction('csrf_token', $this->csrfToken(...)),
            new TwigFunction('path', $this->path(...)),
        ];
    }

    public function asset(string $path): string
    {
        $filePath = $this->publicPath . '/' . ltrim($path, '/');

        if (file_exists($filePath)) {
            return '/' . ltrim($path, '/') . '?v=' . filemtime($filePath);
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    public function flashMessages(): array
    {
        if (!$this->flashMessageService instanceof FlashMessageService) {
            return [];
        }

        return $this->flashMessageService->get();
    }

    public function hasFlash(): bool
    {
        if (!$this->flashMessageService instanceof FlashMessageService) {
            return false;
        }

        return $this->flashMessageService->has();
    }

    public function getFlashType(): string
    {
        if (!$this->flashMessageService instanceof FlashMessageService) {
            return '';
        }

        /** @var array<string, mixed> $_SESSION */
        if (!isset($_SESSION['_flash_messages']) || !is_array($_SESSION['_flash_messages']) || $_SESSION['_flash_messages'] === []) {
            return '';
        }

        /** @var array{type: string, message: string} $firstMessage */
        $firstMessage = $_SESSION['_flash_messages'][0];

        return $firstMessage['type'];
    }

    public function getFlashMessage(): string
    {
        if (!$this->flashMessageService instanceof FlashMessageService) {
            return '';
        }

        /** @var array<string, mixed> $_SESSION */
        if (!isset($_SESSION['_flash_messages']) || !is_array($_SESSION['_flash_messages']) || $_SESSION['_flash_messages'] === []) {
            return '';
        }

        /** @var array{type: string, message: string} $firstMessage */
        $firstMessage = $_SESSION['_flash_messages'][0];

        return $firstMessage['message'];
    }

    public function csrfToken(): string
    {
        if ($this->csrfTokenProvider === null) {
            return '';
        }

        return ($this->csrfTokenProvider)();
    }

    /**
     * @param array<string, int|string> $params
     */
    public function path(string $name, array $params = []): string
    {
        if (!$this->urlGenerator instanceof UrlGeneratorInterface) {
            return '/';
        }

        return $this->urlGenerator->generate($name, $params);
    }
}
