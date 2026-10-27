<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Value\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccessTokenTest extends TestCase
{
    #[Test]
    public function it_reports_a_token_usable_before_expiry(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-10-27T10:00:00Z');
        $token = AccessToken::fromResponse('abc', 3600, $issuedAt);

        self::assertTrue($token->isUsableAt($issuedAt->modify('+10 minutes')));
        self::assertSame('Bearer abc', $token->headerValue());
    }

    #[Test]
    public function it_stops_using_a_token_inside_the_leeway(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-10-27T10:00:00Z');
        $token = AccessToken::fromResponse('abc', 3600, $issuedAt);

        self::assertFalse($token->isUsableAt($issuedAt->modify('+59 minutes')));
    }
}
