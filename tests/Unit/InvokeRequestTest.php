<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Invocation\InvokeRequest;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\UserId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InvokeRequestTest extends TestCase
{
    #[Test]
    public function it_sends_a_message(): void
    {
        self::assertSame('{"message":"Hello"}', InvokeRequest::message('Hello')->toJson()->toJson());
    }

    #[Test]
    public function it_sends_payload_user_and_agent_specific_fields(): void
    {
        $request = InvokeRequest::message('Hello')
            ->withPayload(JsonObject::fromArray(['screen' => 'policy-list']))
            ->withUserId(UserId::fromString('user-1'))
            ->withField('locale', 'en-GB');

        self::assertSame(
            '{"locale":"en-GB","message":"Hello","payload":{"screen":"policy-list"},"user_id":"user-1"}',
            $request->toJson()->toJson(),
        );
    }

    #[Test]
    public function it_refuses_to_override_a_reserved_field(): void
    {
        $this->expectException(InvalidArgument::class);

        InvokeRequest::message('Hello')->withField('message', 'Other');
    }
}
