<?php

declare(strict_types=1);

namespace AgentEngine\Clock;

final readonly class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
