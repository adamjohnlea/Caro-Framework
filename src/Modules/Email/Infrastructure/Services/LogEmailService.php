<?php

declare(strict_types=1);

namespace App\Modules\Email\Infrastructure\Services;

use App\Modules\Email\Application\Services\EmailServiceInterface;
use Override;
use Psr\Log\LoggerInterface;

final readonly class LogEmailService implements EmailServiceInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void {
        $this->logger->info('Email sent', [
            'to' => $to,
            'subject' => $subject,
            'html_length' => strlen($htmlBody),
            'text_length' => strlen($textBody),
        ]);
    }

    #[Override]
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        string $attachmentContent,
        string $attachmentFilename,
    ): void {
        $this->logger->info('Email with attachment sent', [
            'to' => $to,
            'subject' => $subject,
            'body_length' => strlen($body),
            'attachment_filename' => $attachmentFilename,
            'attachment_size' => strlen($attachmentContent),
        ]);
    }
}
