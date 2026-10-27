<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\SessionId;

final readonly class SessionMessage
{
    /**
     * @param list<ToolCall> $toolCalls
     */
    public function __construct(
        public ?string $id,
        public MessageRole $role,
        public string $content,
        public ?SessionId $sessionId = null,
        public ?string $name = null,
        public ?string $timestamp = null,
        public ?string $toolCallId = null,
        public array $toolCalls = [],
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $toolCalls = array_map(
            static fn (mixed $toolCall): ToolCall => ToolCall::fromJson(JsonObject::fromArray((array) $toolCall)),
            $json->optionalArray('tool_calls') ?? [],
        );

        return new self(
            id: $json->optionalString('id'),
            role: MessageRole::fromApi($json->optionalString('role')),
            content: $json->optionalString('content') ?? '',
            sessionId: $json->optionalString('session_id') === null
                ? null
                : SessionId::fromString($json->string('session_id')),
            name: $json->optionalString('name'),
            timestamp: $json->optionalString('timestamp'),
            toolCallId: $json->optionalString('tool_call_id'),
            toolCalls: $toolCalls,
        );
    }
}
