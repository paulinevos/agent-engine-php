<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class BaseUrl
{
    public string $value;

    private function __construct(string $value)
    {
        $trimmed = rtrim($value, '/');

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            throw InvalidArgument::invalidBaseUrl($value);
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function endpoint(string $path): string
    {
        return $this->value . '/' . ltrim($path, '/');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
