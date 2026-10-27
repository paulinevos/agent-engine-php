<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class CustomHeaders
{
    private const MAX_HEADERS = 50;
    private const MAX_VALUE_BYTES = 8192;
    private const PREFIX = 'X-Mdb-Agent-Engine-Custom-';
    private const NAME_PATTERN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';

    /**
     * @param array<string, string> $headers
     */
    public function __construct(private array $headers = [])
    {
        if (count($headers) > self::MAX_HEADERS) {
            throw InvalidArgument::tooManyCustomHeaders(count($headers), self::MAX_HEADERS);
        }

        foreach ($headers as $name => $value) {
            $this->guardHeader($name, $value);
        }
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function fromArray(array $headers): self
    {
        return new self($headers);
    }

    public function with(string $name, string $value): self
    {
        return new self([...$this->without($name), $name => $value]);
    }

    public function isEmpty(): bool
    {
        return $this->headers === [];
    }

    /**
     * @return array<string, string>
     */
    public function prefixed(): array
    {
        $prefixed = [];

        foreach ($this->headers as $name => $value) {
            $prefixed[self::PREFIX . $name] = $value;
        }

        return $prefixed;
    }

    /**
     * @return array<string, string>
     */
    private function without(string $name): array
    {
        return array_filter(
            $this->headers,
            static fn (string $existingName): bool => strcasecmp($existingName, $name) !== 0,
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function guardHeader(string $name, string $value): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw InvalidArgument::invalidHeaderName($name);
        }

        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw InvalidArgument::invalidHeaderValue($name);
        }

        if (strlen($value) > self::MAX_VALUE_BYTES) {
            throw InvalidArgument::customHeaderTooLarge($name, strlen($value), self::MAX_VALUE_BYTES);
        }
    }
}
