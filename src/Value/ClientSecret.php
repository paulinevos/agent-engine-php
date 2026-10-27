<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class ClientSecret
{
    private function __construct(public string $value)
    {
        if ($value === '') {
            throw InvalidArgument::emptyIdentifier('Client secret');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }
}
