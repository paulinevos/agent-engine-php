<?php

declare(strict_types=1);

namespace AgentEngine\Json;

use AgentEngine\Exception\UnexpectedResponse;

/**
 * A decoded JSON object. Agent Engine responses are objects at the top level;
 * values whose shape the API leaves open stay inside this wrapper instead of
 * leaking bare arrays through the public API.
 */
final readonly class JsonObject
{
    /**
     * @param array<string, mixed> $value
     */
    public function __construct(private array $value = [])
    {
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw UnexpectedResponse::invalidJson($json);
        }

        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw UnexpectedResponse::notAnObject($json);
        }

        return new self($decoded);
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function fromArray(array $value): self
    {
        return new self($value);
    }

    public function with(string $key, mixed $value): self
    {
        return new self([...$this->value, $key => $value]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->value);
    }

    public function string(string $key): string
    {
        return $this->require($key, 'string');
    }

    public function optionalString(string $key): ?string
    {
        return $this->optional($key, 'string');
    }

    public function integer(string $key): int
    {
        return $this->require($key, 'integer');
    }

    public function optionalInteger(string $key): ?int
    {
        return $this->optional($key, 'integer');
    }

    public function number(string $key): float|int
    {
        $value = $this->value[$key] ?? null;

        if (!is_int($value) && !is_float($value)) {
            throw UnexpectedResponse::missingField($key);
        }

        return $value;
    }

    public function optionalNumber(string $key): float|int|null
    {
        $value = $this->value[$key] ?? null;

        return is_int($value) || is_float($value) ? $value : null;
    }

    public function boolean(string $key): bool
    {
        return $this->require($key, 'boolean');
    }

    public function optionalBoolean(string $key): ?bool
    {
        return $this->optional($key, 'boolean');
    }

    public function object(string $key): self
    {
        $value = $this->value[$key] ?? null;

        if (!is_array($value)) {
            throw UnexpectedResponse::missingField($key);
        }

        return new self($value);
    }

    public function optionalObject(string $key): ?self
    {
        $value = $this->value[$key] ?? null;

        return is_array($value) ? new self($value) : null;
    }

    /**
     * @return list<mixed>
     */
    public function array(string $key): array
    {
        $value = $this->value[$key] ?? null;

        if (!is_array($value)) {
            throw UnexpectedResponse::missingField($key);
        }

        return array_values($value);
    }

    /**
     * @return list<mixed>|null
     */
    public function optionalArray(string $key): ?array
    {
        $value = $this->value[$key] ?? null;

        return is_array($value) ? array_values($value) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->value;
    }

    public function toJson(): string
    {
        return json_encode(
            $this->normalise($this->value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * An empty PHP array is ambiguous; inside a JSON object it means an empty
     * object, which is the only interpretation the Agent Engine accepts for a
     * request body such as an interrupt with no step number.
     */
    private function normalise(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $this->normalise($value->value);
        }

        if (!is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return new \stdClass();
        }

        if (array_is_list($value)) {
            return array_map($this->normalise(...), $value);
        }

        $object = new \stdClass();

        foreach ($value as $key => $item) {
            $object->{$key} = $this->normalise($item);
        }

        return $object;
    }

    private function require(string $key, string $type): mixed
    {
        $value = $this->value[$key] ?? null;

        if (gettype($value) !== $type) {
            throw UnexpectedResponse::missingField($key);
        }

        return $value;
    }

    private function optional(string $key, string $type): mixed
    {
        $value = $this->value[$key] ?? null;

        return gettype($value) === $type ? $value : null;
    }
}
