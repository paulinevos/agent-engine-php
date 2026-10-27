<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class ExecutionId
{
    private function __construct(public string $value)
    {
        if ($value === '') {
            throw InvalidArgument::emptyIdentifier('Execution ID');
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
