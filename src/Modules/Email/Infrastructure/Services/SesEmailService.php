<?php

declare(strict_types=1);

namespace App\Modules\Email\Infrastructure\Services;

use App\Modules\Email\Application\Services\EmailServiceInterface;
use Aws\SesV2\SesV2Client;
use Override;

final readonly class SesEmailService implements EmailServiceInterface
{
    private SesV2Client $client;
    private string $fromAddress;

    /**
     * @param array{region: string, access_key: string, secret_key: string, from_address: string} $config
     */
    public function __construct(array $config)
    {
        $this->client = new SesV2Client([
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['access_key'],
                'secret' => $config['secret_key'],
            ],
        ]);
        $this->fromAddress = $config['from_address'];
    }

    #[Override]
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void {
        $this->client->sendEmail([
            'FromEmailAddress' => $this->fromAddress,
            'Destination' => [
                'ToAddresses' => [$to],
            ],
            'Content' => [
                'Simple' => [
                    'Subject' => [
                        'Data' => $subject,
                        'Charset' => 'UTF-8',
                    ],
                    'Body' => [
                        'Html' => [
                            'Data' => $htmlBody,
                            'Charset' => 'UTF-8',
                        ],
                        'Text' => [
                            'Data' => $textBody,
                            'Charset' => 'UTF-8',
                        ],
                    ],
                ],
            ],
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
        $boundary = bin2hex(random_bytes(16));
        $rawMessage = $this->buildMimeMessage($to, $subject, $body, $attachmentContent, $attachmentFilename, $boundary);

        $this->client->sendEmail([
            'Content' => [
                'Raw' => [
                    'Data' => $rawMessage,
                ],
            ],
        ]);
    }

    private function buildMimeMessage(
        string $to,
        string $subject,
        string $body,
        string $attachmentContent,
        string $attachmentFilename,
        string $boundary,
    ): string {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedAttachment = chunk_split(base64_encode($attachmentContent));

        return "From: {$this->fromAddress}\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$encodedSubject}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n"
            . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 7bit\r\n"
            . "\r\n"
            . "{$body}\r\n"
            . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/octet-stream; name=\"{$attachmentFilename}\"\r\n"
            . "Content-Disposition: attachment; filename=\"{$attachmentFilename}\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "\r\n"
            . "{$encodedAttachment}\r\n"
            . "--{$boundary}--\r\n";
    }
}
