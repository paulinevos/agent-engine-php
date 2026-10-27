<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Json\JsonObject;

/**
 * Agent-defined review context. The fields vary by agent, so this wrapper
 * exposes the protocol-level pieces and the raw object for the rest.
 */
final readonly class SuspendContext
{
    public function __construct(private JsonObject $data)
    {
    }

    public static function fromJsonObject(JsonObject $data): self
    {
        return new self($data);
    }

    /**
     * @return list<string>
     */
    public function allowedDecisions(): array
    {
        $decisions = $this->data->optionalArray('allowed_decisions') ?? [];

        return array_values(array_filter($decisions, 'is_string'));
    }

    public function has(string $key): bool
    {
        return $this->data->has($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data->toArray();
    }
}
