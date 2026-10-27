<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\DependencyInjection;

use InvalidArgumentException;

final readonly class ConfigValues
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values)
    {
    }

    public function string(string $key): string
    {
        $value = $this->values[$key] ?? null;

        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Configuration value "%s" must be a string.', $key));
        }

        return $value;
    }

    public function optionalString(string $key): ?string
    {
        if (($this->values[$key] ?? null) === null) {
            return null;
        }

        return $this->string($key);
    }

    public function optionalInteger(string $key): ?int
    {
        $value = $this->values[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('Configuration value "%s" must be an integer.', $key));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Configuration value "%s" must be an array.', $key));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        $strings = [];

        foreach ($this->array($key) as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf('Every entry in "%s" must be a string.', $key));
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @return array<string, string>
     */
    public function stringMap(string $key): array
    {
        $mapped = [];

        foreach ($this->array($key) as $name => $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf('Every entry in "%s" must map a string name to a string value.', $key));
            }

            $mapped[$name] = $value;
        }

        return $mapped;
    }
}
