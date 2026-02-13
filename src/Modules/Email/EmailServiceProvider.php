<?php

declare(strict_types=1);

namespace App\Modules\Email;

use App\Modules\Email\Application\Services\EmailServiceInterface;
use App\Modules\Email\Infrastructure\Services\LogEmailService;
use App\Modules\Email\Infrastructure\Services\SesEmailService;
use App\Shared\Providers\ServiceProvider;
use Override;
use Psr\Log\LoggerInterface;

final class EmailServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->container->set(
            EmailServiceInterface::class,
            function (): SesEmailService|LogEmailService {
                if ($this->config['ses']['access_key'] !== '' && $this->config['ses']['secret_key'] !== '') {
                    return new SesEmailService($this->config['ses']);
                }

                /** @var LoggerInterface $logger */
                $logger = $this->container->get(LoggerInterface::class);

                return new LogEmailService($logger);
            },
        );
    }
}
