<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class WorkspaceId
{
    private function __construct(public string $value)
    {
        if ($value === '') {
            throw InvalidArgument::emptyIdentifier('Workspace ID');
        }

        if (preg_match('/\s/', $value) === 1) {
            throw InvalidArgument::invalidIdentifier('Workspace ID', $value);
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
