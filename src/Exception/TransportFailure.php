<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

final class TransportFailure extends AgentEngineException
{
    public static function forRequest(string $method, string $uri, \Throwable $previous): self
    {
        return new self(
            sprintf('Could not send %s %s: %s', $method, $uri, $previous->getMessage()),
            0,
            $previous,
        );
    }

}
