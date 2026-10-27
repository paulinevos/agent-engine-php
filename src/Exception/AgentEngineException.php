<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

/**
 * Base type for every failure raised by this library. Callers that only need
 * a single catch boundary can catch this; callers that react to a specific
 * failure catch the concrete subclass.
 */
class AgentEngineException extends \RuntimeException
{
}
