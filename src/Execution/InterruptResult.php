<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\ExecutionId;

final readonly class InterruptResult
{
    /**
     * @param list<int> $interruptedSteps
     */
    public function __construct(
        public ExecutionId $executionId,
        public bool $interrupted,
        public array $interruptedSteps,
        public bool $pending,
        public InterruptOutcome $outcome,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $steps = $json->optionalArray('interrupted_steps') ?? [];

        return new self(
            executionId: ExecutionId::fromString($json->string('execution_id')),
            interrupted: $json->optionalBoolean('interrupted') ?? false,
            interruptedSteps: array_values(array_filter($steps, 'is_int')),
            pending: $json->optionalBoolean('pending') ?? false,
            outcome: InterruptOutcome::fromApi($json->optionalString('outcome')),
        );
    }
}
