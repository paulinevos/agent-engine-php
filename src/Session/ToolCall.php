<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;

final readonly class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public JsonObject $arguments,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            id: $json->optionalString('id') ?? '',
            name: $json->optionalString('name') ?? '',
            arguments: $json->optionalObject('arguments') ?? new JsonObject(),
        );
    }
}
