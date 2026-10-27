<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

enum InterruptOutcome: string
{
    case Aborted = 'aborted';
    case Armed = 'armed';
    case AlreadyArmed = 'already_armed';
    case NoopTerminal = 'noop_terminal';
    case Noop = 'noop';
    case Unknown = 'unknown';

    public static function fromApi(?string $value): self
    {
        return $value === null ? self::Unknown : (self::tryFrom($value) ?? self::Unknown);
    }
}
