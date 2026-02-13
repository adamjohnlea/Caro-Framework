<?php

declare(strict_types=1);

namespace App\Shared\Session;

final class FlashMessageService
{
    private const string SESSION_KEY = '_flash_messages';

    /**
     * Add a flash message.
     */
    public function flash(string $type, string $message): void
    {
        /** @var array<string, mixed> $_SESSION */
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Get all flash messages and clear them.
     *
     * @return list<array{type: string, message: string}>
     */
    public function get(): array
    {
        /** @var array<string, mixed> $_SESSION */
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return [];
        }

        /** @var list<array{type: string, message: string}> $messages */
        $messages = $_SESSION[self::SESSION_KEY];
        $_SESSION[self::SESSION_KEY] = [];

        return $messages;
    }

    /**
     * Check if there are any flash messages.
     */
    public function has(): bool
    {
        /** @var array<string, mixed> $_SESSION */
        return isset($_SESSION[self::SESSION_KEY])
            && is_array($_SESSION[self::SESSION_KEY])
            && $_SESSION[self::SESSION_KEY] !== [];
    }
}
