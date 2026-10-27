<?php

declare(strict_types=1);

namespace AgentEngine\Memory;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Json\JsonObject;
use AgentEngine\Session\MessageRole;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\UserId;

final readonly class RecordTurn
{
    /**
     * @param list<array<string, mixed>>|null $toolCalls
     */
    private function __construct(
        public string $role,
        public SessionId $sessionId,
        public UserId $userId,
        public string $content = '',
        public ?string $agentId = null,
        public ?string $modelName = null,
        public ?string $idempotencyKey = null,
        public ?bool $isError = null,
        public ?string $toolName = null,
        public ?string $toolCallId = null,
        public ?array $toolCalls = null,
        public ?JsonObject $metadata = null,
    ) {
        if ($this->role === '') {
            throw InvalidArgument::emptyIdentifier('Turn role');
        }
    }

    public static function of(
        string $role,
        SessionId $sessionId,
        UserId $userId,
        string $content = '',
        ?string $agentId = null,
        ?string $modelName = null,
    ): self {
        return new self($role, $sessionId, $userId, $content, $agentId, $modelName);
    }

    public static function user(SessionId $sessionId, UserId $userId, string $content): self
    {
        return self::of(MessageRole::User->value, $sessionId, $userId, $content);
    }

    public static function assistant(SessionId $sessionId, UserId $userId, string $content): self
    {
        return self::of(MessageRole::Assistant->value, $sessionId, $userId, $content);
    }

    public function withIdempotencyKey(string $idempotencyKey): self
    {
        return new self(
            $this->role,
            $this->sessionId,
            $this->userId,
            $this->content,
            $this->agentId,
            $this->modelName,
            $idempotencyKey,
            $this->isError,
            $this->toolName,
            $this->toolCallId,
            $this->toolCalls,
            $this->metadata,
        );
    }

    public function withMetadata(JsonObject $metadata): self
    {
        return new self(
            $this->role,
            $this->sessionId,
            $this->userId,
            $this->content,
            $this->agentId,
            $this->modelName,
            $this->idempotencyKey,
            $this->isError,
            $this->toolName,
            $this->toolCallId,
            $this->toolCalls,
            $metadata,
        );
    }

    public function toJson(): JsonObject
    {
        $body = [
            'role' => $this->role,
            'session_id' => $this->sessionId->value,
            'user_id' => $this->userId->value,
            'content' => $this->content,
        ];

        $body += array_filter([
            'agent_id' => $this->agentId,
            'model_name' => $this->modelName,
            'idempotency_key' => $this->idempotencyKey,
            'is_error' => $this->isError,
            'tool_name' => $this->toolName,
            'tool_call_id' => $this->toolCallId,
            'tool_calls' => $this->toolCalls,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        if ($this->metadata !== null) {
            $body['metadata'] = $this->metadata->toArray();
        }

        return JsonObject::fromArray($body);
    }
}
