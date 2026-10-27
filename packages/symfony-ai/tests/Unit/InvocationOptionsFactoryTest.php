<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Unit;

use AgentEngine\SymfonyAi\Platform\InvocationOptionsFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class InvocationOptionsFactoryTest extends TestCase
{
    #[Test]
    public function it_builds_empty_options_by_default(): void
    {
        $options = (new InvocationOptionsFactory())->from([]);

        self::assertNull($options->sessionId);
        self::assertNull($options->timeoutSeconds);
        self::assertFalse($options->testSession);
        self::assertFalse($options->customOutput);
        self::assertSame([], $options->headers());
    }

    #[Test]
    public function it_maps_session_headers_and_flags(): void
    {
        $options = (new InvocationOptionsFactory())->from([
            InvocationOptionsFactory::SESSION_ID => 'session-1',
            InvocationOptionsFactory::CUSTOM_HEADERS => ['Tenant-Id' => 'acme'],
            InvocationOptionsFactory::TIMEOUT_SECONDS => 30,
            InvocationOptionsFactory::TEST_SESSION => true,
            InvocationOptionsFactory::CUSTOM_OUTPUT => true,
        ]);

        self::assertSame('session-1', $options->sessionId?->value);
        self::assertSame(30, $options->timeoutSeconds);
        self::assertTrue($options->testSession);
        self::assertTrue($options->customOutput);
        self::assertSame(
            [
                'X-Mdb-Agent-Engine-Custom-Tenant-Id' => 'acme',
                'X-Session-ID' => 'session-1',
                'X-Agent-Engine-Invoke-Timeout-Seconds' => '30',
                'X-Agent-Engine-Test-Session' => 'true',
            ],
            $options->headers(),
        );
    }

    #[Test]
    public function it_rejects_a_non_string_custom_header(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InvocationOptionsFactory())->from([
            InvocationOptionsFactory::CUSTOM_HEADERS => ['Tenant-Id' => 42],
        ]);
    }

    #[Test]
    public function it_rejects_a_non_integer_timeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InvocationOptionsFactory())->from([
            InvocationOptionsFactory::TIMEOUT_SECONDS => '30',
        ]);
    }
}
