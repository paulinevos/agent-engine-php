<?php

declare(strict_types=1);

namespace AgentEngine\Session;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';
    case Tool = 'tool';
    case Unknown = 'unknown';

    public static function fromApi(?string $value): self
    {
        return $value === null ? self::Unknown : (self::tryFrom($value) ?? self::Unknown);
    }
}
