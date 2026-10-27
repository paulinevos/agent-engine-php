<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Value\SessionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionIdTest extends TestCase
{
    #[Test]
    public function it_accepts_the_documented_character_set(): void
    {
        self::assertSame('my-session_1', SessionId::fromString('my-session_1')->value);
        self::assertSame(str_repeat('a', 128), SessionId::fromString(str_repeat('a', 128))->value);
    }

    #[Test]
    public function it_rejects_spaces_and_overlong_ids(): void
    {
        $this->expectException(InvalidArgument::class);

        SessionId::fromString('has space');
    }

    #[Test]
    public function it_rejects_an_overlong_id(): void
    {
        $this->expectException(InvalidArgument::class);

        SessionId::fromString(str_repeat('a', 129));
    }
}
