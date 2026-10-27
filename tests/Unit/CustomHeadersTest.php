<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Value\CustomHeaders;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CustomHeadersTest extends TestCase
{
    #[Test]
    public function it_prefixes_headers_for_the_gateway(): void
    {
        $headers = CustomHeaders::fromArray(['Tenant-Id' => 'acme', 'Authorization' => 'user-1']);

        self::assertSame([
            'X-Mdb-Agent-Engine-Custom-Tenant-Id' => 'acme',
            'X-Mdb-Agent-Engine-Custom-Authorization' => 'user-1',
        ], $headers->prefixed());
    }

    #[Test]
    public function it_adds_headers_immutably(): void
    {
        $original = CustomHeaders::none();
        $extended = $original->with('Tenant-Id', 'acme');

        self::assertTrue($original->isEmpty());
        self::assertFalse($extended->isEmpty());
    }

    #[Test]
    public function it_rejects_too_many_headers(): void
    {
        $headers = [];

        for ($index = 0; $index < 51; $index++) {
            $headers['X-Custom-' . $index] = 'value';
        }

        $this->expectException(InvalidArgument::class);

        CustomHeaders::fromArray($headers);
    }

    #[Test]
    public function it_rejects_a_line_break_in_a_value(): void
    {
        $this->expectException(InvalidArgument::class);

        CustomHeaders::fromArray(['Tenant-Id' => "acme\r\nInjected: yes"]);
    }

    #[Test]
    public function it_rejects_an_invalid_name(): void
    {
        $this->expectException(InvalidArgument::class);

        CustomHeaders::fromArray(['Tenant Id' => 'acme']);
    }
}
