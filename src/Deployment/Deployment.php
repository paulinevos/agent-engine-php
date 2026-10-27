<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\DeploymentId;

final readonly class Deployment
{
    /**
     * @param list<DeploymentComponent> $components
     */
    public function __construct(
        public DeploymentId $deploymentId,
        public DeploymentStatus $status,
        public string $rawStatus,
        public array $components = [],
        public ?DeploymentHealth $health = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $components = array_map(
            static fn (mixed $component): DeploymentComponent => DeploymentComponent::fromJson(
                JsonObject::fromArray((array) $component),
            ),
            $json->optionalArray('components') ?? [],
        );
        $status = $json->optionalString('status') ?? 'unknown';

        return new self(
            deploymentId: DeploymentId::fromString($json->string('deployment_id')),
            status: DeploymentStatus::fromApi($status),
            rawStatus: $status,
            components: $components,
            health: $json->optionalObject('health') === null
                ? null
                : DeploymentHealth::fromJson($json->object('health')),
        );
    }

    public function isHealthy(): bool
    {
        if (!$this->status->isSuccessful()) {
            return false;
        }

        if ($this->health === null || !$this->health->available) {
            return false;
        }

        return $this->componentsReachable();
    }

    private function componentsReachable(): bool
    {
        foreach ($this->components as $component) {
            if (!$component->isReady()) {
                return false;
            }
        }

        return true;
    }
}
