<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\ExecutionId;
use AgentEngine\Value\SessionId;

final readonly class Execution
{
    public function __construct(
        public ExecutionId $executionId,
        public ExecutionStatus $status,
        public string $rawStatus,
        public ?SessionId $sessionId = null,
        public ?string $message = null,
        public mixed $result = null,
        public ?string $error = null,
        public ?string $errorCode = null,
        public ?SuspendContext $suspendContext = null,
        public ?string $suspendReason = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?string $userId = null,
        public ?JsonObject $startupFailure = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $status = $json->optionalString('status') ?? 'unknown';
        $sessionId = $json->optionalString('session_id');

        return new self(
            executionId: ExecutionId::fromString($json->string('execution_id')),
            status: ExecutionStatus::fromApi($status),
            rawStatus: $status,
            sessionId: $sessionId === null ? null : SessionId::fromString($sessionId),
            message: $json->optionalString('message'),
            result: $json->toArray()['result'] ?? null,
            error: $json->optionalString('error'),
            errorCode: $json->optionalString('error_code'),
            suspendContext: $json->optionalObject('suspend_context') === null
                ? null
                : SuspendContext::fromJsonObject($json->object('suspend_context')),
            suspendReason: $json->optionalString('suspend_reason'),
            createdAt: $json->optionalString('created_at'),
            updatedAt: $json->optionalString('updated_at'),
            userId: $json->optionalString('user_id'),
            startupFailure: $json->optionalObject('startup_failure'),
        );
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isSuspended(): bool
    {
        return $this->status->isSuspended();
    }
}
