<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

use AgentEngine\Value\ExecutionId;

final class StreamFailed extends AgentEngineException
{
    public function __construct(
        public readonly string $messageFromAgent,
        public readonly ?ExecutionId $executionId = null,
    ) {
        parent::__construct($messageFromAgent);
    }
}
