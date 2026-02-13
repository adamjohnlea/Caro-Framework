<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Session\FlashMessageService;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $publicPath,
        private readonly ?FlashMessageService $flashMessageService = null,
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
}
