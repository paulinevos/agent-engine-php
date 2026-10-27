<?php

declare(strict_types=1);

namespace AgentEngine\Clock;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
