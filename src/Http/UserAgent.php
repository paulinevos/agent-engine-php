<?php

declare(strict_types=1);

namespace AgentEngine\Http;

final readonly class UserAgent
{
    public const VERSION = '0.1.0';

    public function __construct(public string $value = 'agent-engine-php/' . self::VERSION)
    {
    }

    public static function default(): self
    {
        return new self();
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
