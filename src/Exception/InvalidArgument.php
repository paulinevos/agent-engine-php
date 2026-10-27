<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

final class InvalidArgument extends AgentEngineException
{
    public static function emptyIdentifier(string $name): self
    {
        return new self(sprintf('%s cannot be empty.', $name));
    }

    public static function invalidIdentifier(string $name, string $value): self
    {
        return new self(sprintf('%s "%s" is not valid.', $name, $value));
    }

    public static function invalidBaseUrl(string $value): self
    {
        return new self(sprintf('Base URL "%s" is not an absolute URL.', $value));
    }

    public static function invalidContractVersion(string $value): self
    {
        return new self(sprintf(
            'Contract version "%s" must look like 2026-09-20-preview.',
            $value,
        ));
    }

    public static function tooManyCustomHeaders(int $count, int $maximum): self
    {
        return new self(sprintf(
            'At most %d custom headers are allowed, %d were given.',
            $maximum,
            $count,
        ));
    }

    public static function customHeaderTooLarge(string $name, int $bytes, int $maximum): self
    {
        return new self(sprintf(
            'Custom header "%s" is %d bytes; the maximum is %d.',
            $name,
            $bytes,
            $maximum,
        ));
    }

    public static function invalidHeaderName(string $name): self
    {
        return new self(sprintf('"%s" is not a valid HTTP header name.', $name));
    }

    public static function invalidHeaderValue(string $name): self
    {
        return new self(sprintf('Header "%s" contains a line break.', $name));
    }

    public static function timeoutOutOfRange(int $seconds, int $minimum, int $maximum): self
    {
        return new self(sprintf(
            'Timeout must be between %d and %d seconds, %d was given.',
            $minimum,
            $maximum,
            $seconds,
        ));
    }

    public static function negativeInt(string $name, int $value): self
    {
        return new self(sprintf('%s must not be negative, %d was given.', $name, $value));
    }

    public static function outOfRange(string $name, int $value, int $minimum, int $maximum): self
    {
        return new self(sprintf(
            '%s must be between %d and %d, %d was given.',
            $name,
            $minimum,
            $maximum,
            $value,
        ));
    }

    public static function belowMinimum(string $name, int $value, int $minimum): self
    {
        return new self(sprintf('%s must be at least %d, %d was given.', $name, $minimum, $value));
    }

    public static function reservedField(string $name): self
    {
        return new self(sprintf(
            '"%s" is a reserved invoke field; use the dedicated method instead.',
            $name,
        ));
    }

    public static function unknownStreamChunkType(string $chunkType): self
    {
        return new self(sprintf('Unknown stream chunk type "%s".', $chunkType));
    }
}
