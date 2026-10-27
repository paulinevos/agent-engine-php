<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class SessionId
{
    private function __construct(public string $value)
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/', $value) !== 1) {
            throw InvalidArgument::invalidIdentifier('Session ID', $value);
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
