<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

final class UnexpectedResponse extends AgentEngineException
{
    public static function invalidJson(string $body): self
    {
        return new self(sprintf(
            'Expected valid JSON, got "%s".',
            substr($body, 0, 500),
        ));
    }

    public static function notAnObject(string $body): self
    {
        return new self(sprintf(
            'Expected a JSON object, got "%s".',
            substr($body, 0, 500),
        ));
    }

    public static function missingField(string $field): self
    {
        return new self(sprintf('The response is missing the required field "%s".', $field));
    }
}
