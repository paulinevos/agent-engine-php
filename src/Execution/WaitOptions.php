<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Exception\InvalidArgument;

/**
 * Polling policy for waiting on an execution. The defaults follow the CLI's
 * ten-minute invoke timeout and a one-second poll interval.
 */
final readonly class WaitOptions
{
    public function __construct(
        public int $timeoutSeconds = 600,
        public int $pollIntervalMilliseconds = 1000,
    ) {
        if ($timeoutSeconds < 1) {
            throw InvalidArgument::belowMinimum('Timeout in seconds', $timeoutSeconds, 1);
        }

        if ($pollIntervalMilliseconds < 1) {
            throw InvalidArgument::belowMinimum('Poll interval in milliseconds', $pollIntervalMilliseconds, 1);
        }
    }
}
