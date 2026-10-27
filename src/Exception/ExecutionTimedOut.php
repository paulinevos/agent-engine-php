<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

use AgentEngine\Value\ExecutionId;

final class ExecutionTimedOut extends AgentEngineException
{
    public static function waitingFor(ExecutionId $executionId, int $timeoutSeconds): self
    {
        return new self(sprintf(
            'Execution %s did not settle within %d seconds.',
            $executionId->value,
            $timeoutSeconds,
        ));
    }
}
