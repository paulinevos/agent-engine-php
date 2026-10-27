<?php

declare(strict_types=1);

namespace AgentEngine\Invocation;

use AgentEngine\Execution\ExecutionStatus;
use AgentEngine\Execution\SuspendContext;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\ExecutionId;
use AgentEngine\Value\SessionId;

final readonly class InvokeResult
{
    public function __construct(
        public bool $success,
        public ExecutionId $executionId,
        public ExecutionStatus $status,
        public string $rawStatus,
        public ?string $response = null,
        public ?SessionId $sessionId = null,
        public ?SuspendContext $suspendContext = null,
        public ?string $suspendReason = null,
        public ?string $error = null,
        public ?string $errorCode = null,
    ) {
    }

    public static function fromJson(JsonObject $json, ?SessionId $sessionId): self
    {
        $status = $json->optionalString('status') ?? 'unknown';

        return new self(
            success: $json->optionalBoolean('success') ?? false,
            executionId: ExecutionId::fromString($json->string('execution_id')),
            status: ExecutionStatus::fromApi($status),
            rawStatus: $status,
            response: $json->optionalString('response'),
            sessionId: $sessionId,
            suspendContext: $json->optionalObject('suspend_context') === null
                ? null
                : SuspendContext::fromJsonObject($json->object('suspend_context')),
            suspendReason: $json->optionalString('suspend_reason'),
            error: $json->optionalString('error'),
            errorCode: $json->optionalString('error_code'),
        );
    }

    public function requiresReview(): bool
    {
        return $this->status->isSuspended() || $this->suspendContext !== null;
    }

    public function text(): string
    {
        return $this->response ?? '';
    }
}
