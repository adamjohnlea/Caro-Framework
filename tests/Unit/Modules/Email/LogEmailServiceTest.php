<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Email;

use App\Modules\Email\Infrastructure\Services\LogEmailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LogEmailServiceTest extends TestCase
{
    public function test_send_logs_email_details(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Email sent', [
                'to' => 'user@example.com',
                'subject' => 'Test Subject',
                'html_length' => 20,
                'text_length' => 14,
            ]);

        $service = new LogEmailService($logger);
        $service->send(
            'user@example.com',
            'Test Subject',
            '<p>Hello, World!</p>',
            'Hello, World!.',
        );
    }

    public function test_send_with_attachment_logs_email_details(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Email with attachment sent', [
                'to' => 'user@example.com',
                'subject' => 'Report',
                'body_length' => 9,
                'attachment_filename' => 'report.pdf',
                'attachment_size' => 11,
            ]);

        $service = new LogEmailService($logger);
        $service->sendWithAttachment(
            'user@example.com',
            'Report',
            'See file.',
            'PDF content',
            'report.pdf',
        );
    }
}
