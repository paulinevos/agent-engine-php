<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class ClientId
{
    private function __construct(public string $value)
    {
        if ($value === '') {
            throw InvalidArgument::emptyIdentifier('Client ID');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
