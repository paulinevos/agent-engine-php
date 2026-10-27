<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Support;

use AgentEngine\Clock\Clock;

final readonly class FixedClock implements Clock
{
    public function __construct(private \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
