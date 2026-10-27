<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\ExecutionId;

final readonly class ResumeResult
{
    public function __construct(
        public ExecutionId $executionId,
        public string $status,
        public bool $success,
        public ?string $error = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            executionId: ExecutionId::fromString($json->string('execution_id')),
            status: $json->optionalString('status') ?? 'unknown',
            success: $json->optionalBoolean('success') ?? false,
            error: $json->optionalString('error'),
        );
    }
}
