<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Session;

use App\Shared\Session\FlashMessageService;
use PHPUnit\Framework\TestCase;

final class FlashMessageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_SESSION = [];
    }

    public function test_flash_stores_message(): void
    {
        $service = new FlashMessageService();
        $service->flash('success', 'User created.');

        $this->assertTrue($service->has());
    }

    public function test_get_returns_messages(): void
    {
        $service = new FlashMessageService();
        $service->flash('success', 'Done!');
        $service->flash('error', 'Failed!');

        $messages = $service->get();

        $this->assertCount(2, $messages);
        $this->assertSame('success', $messages[0]['type']);
        $this->assertSame('Done!', $messages[0]['message']);
        $this->assertSame('error', $messages[1]['type']);
        $this->assertSame('Failed!', $messages[1]['message']);
    }

    public function test_get_clears_messages(): void
    {
        $service = new FlashMessageService();
        $service->flash('success', 'Done!');

        $service->get();

        $this->assertFalse($service->has());
        $this->assertSame([], $service->get());
    }

    public function test_has_returns_false_when_no_messages(): void
    {
        $service = new FlashMessageService();

        $this->assertFalse($service->has());
    }
}
