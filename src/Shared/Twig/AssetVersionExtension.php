<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AssetVersionExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $publicPath,
    ) {
    }

    /** @return list<TwigFunction> */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', $this->asset(...)),
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
}
