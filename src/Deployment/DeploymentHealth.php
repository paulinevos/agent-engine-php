<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Json\JsonObject;

final readonly class DeploymentHealth
{
    public function __construct(
        public bool $available,
        public ?string $checkedAt = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            available: $json->optionalBoolean('available') ?? false,
            checkedAt: $json->optionalString('checked_at'),
        );
    }
}
