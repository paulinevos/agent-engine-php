<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Json\JsonObject;

final readonly class DeploymentComponent
{
    public function __construct(
        public string $name,
        public bool $available,
        public int $replicas,
        public int $readyReplicas,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            name: $json->optionalString('name') ?? 'unknown',
            available: $json->optionalBoolean('available') ?? false,
            replicas: $json->optionalInteger('replicas') ?? 0,
            readyReplicas: $json->optionalInteger('ready_replicas') ?? 0,
        );
    }

    public function isReady(): bool
    {
        return $this->available && $this->replicas === $this->readyReplicas;
    }
}
