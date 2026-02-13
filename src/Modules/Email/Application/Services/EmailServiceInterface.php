<?php

declare(strict_types=1);

namespace App\Modules\Email\Application\Services;

interface EmailServiceInterface
{
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void;

    public function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        string $attachmentContent,
        string $attachmentFilename,
    ): void;
}
