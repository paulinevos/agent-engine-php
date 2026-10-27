<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class DeploymentId
{
    private function __construct(public string $value)
    {
        if ($value === '') {
            throw InvalidArgument::emptyIdentifier('Deployment ID');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
