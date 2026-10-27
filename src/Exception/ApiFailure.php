<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

class ApiFailure extends AgentEngineException
{
    public function __construct(public readonly ErrorDetails $details)
    {
        parent::__construct($details->describe());
    }
}
