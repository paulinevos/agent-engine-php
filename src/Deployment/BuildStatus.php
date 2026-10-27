<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

enum BuildStatus: string
{
    case Accepted = 'accepted';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public static function fromApi(?string $value): self
    {
        return $value === null ? self::Unknown : (self::tryFrom($value) ?? self::Unknown);
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Cancelled => true,
            default => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }
}
