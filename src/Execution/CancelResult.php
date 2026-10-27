<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\ExecutionId;

final readonly class CancelResult
{
    public function __construct(
        public ExecutionId $executionId,
        public bool $cancelled,
        public string $status,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            executionId: ExecutionId::fromString($json->string('execution_id')),
            cancelled: $json->optionalBoolean('cancelled') ?? false,
            status: $json->optionalString('status') ?? 'unknown',
        );
    }
}
