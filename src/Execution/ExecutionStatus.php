<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

enum ExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Suspended = 'suspended';
    case Resuming = 'resuming';
    case Completed = 'completed';
    case Error = 'error';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public static function fromApi(string $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Error, self::Cancelled => true,
            default => false,
        };
    }

    public function isSuspended(): bool
    {
        return $this === self::Suspended;
    }
}
