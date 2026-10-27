<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

enum DeploymentStatus: string
{
    case Successful = 'successful';
    case Failed = 'failed';
    case InProgress = 'in_progress';
    case Pending = 'pending';
    case Unknown = 'unknown';

    public static function fromApi(?string $value): self
    {
        return $value === null ? self::Unknown : (self::tryFrom($value) ?? self::Unknown);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Successful;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    public function isTerminal(): bool
    {
        return $this->isSuccessful() || $this->isFailed();
    }
}
